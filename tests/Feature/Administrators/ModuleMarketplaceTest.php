<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

it('shows installed modules in the marketplace and persists status changes', function (): void {
    $statusesPath = storage_path('framework/testing/module-marketplace-'.uniqid().'.json');
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->assignRole(UserRole::Admin->value);

    try {
        File::put($statusesPath, json_encode([
            'Announcement' => true,
            'Cashier' => true,
            'Inventory' => true,
            'LibrarySystem' => true,
            'NotificationCenter' => true,
            'StudentMedicalRecords' => true,
        ], JSON_THROW_ON_ERROR));

        config([
            'modules-marketplace.enabled' => true,
            'modules-marketplace.registry_url' => null,
            'modules.statuses-file' => $statusesPath,
        ]);

        $this->app->forgetInstance(App\Modules\ModuleStateRepository::class);
        $this->app->forgetInstance(Nwidart\Modules\Contracts\ActivatorInterface::class);
        $this->app->forgetInstance(Nwidart\Modules\Contracts\RepositoryInterface::class);
        $this->app->forgetInstance('modules');

        actingAs($user)
            ->get(portalUrlForAdministrators('/administrators/module-marketplace'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
                ->component('administrators/module-marketplace/index', false)
                ->where('marketplace.enabled', true)
                ->has('marketplace.modules', 6)
                ->where('marketplace.modules.0.installed', true)
                ->where('marketplace.modules.0.installation_source', 'source'));

        actingAs($user)
            ->post(portalUrlForAdministrators('/administrators/module-marketplace/Announcement/disable'))
            ->assertRedirect();

        expect(json_decode(File::get($statusesPath), true, 512, JSON_THROW_ON_ERROR)['Announcement'])->toBeFalse()
            ->and((bool) DB::table('module_installations')->where('module_name', 'Announcement')->value('enabled'))->toBeFalse()
            ->and((bool) DB::table('module_installations')->where('module_name', 'Announcement')->value('restart_required'))->toBeTrue();
    } finally {
        File::delete($statusesPath);
    }
});

it('prevents non-super administrators from changing module status', function (): void {
    $user = User::factory()->create(['role' => UserRole::Registrar]);

    actingAs($user)
        ->post(portalUrlForAdministrators('/administrators/module-marketplace/Announcement/disable'))
        ->assertForbidden();
});
