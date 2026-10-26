<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
                ->has('marketplace.modules', 7)
                ->where('marketplace.modules.0.installed', true)
                ->where('marketplace.modules.0.installation_source', 'source'));

        Cache::put('admin-navigation-routes:v2', ['stale-route']);

        actingAs($user)
            ->post(portalUrlForAdministrators('/administrators/module-marketplace/Announcement/disable'))
            ->assertRedirect();

        expect(json_decode(File::get($statusesPath), true, 512, JSON_THROW_ON_ERROR)['Announcement'])->toBeFalse()
            ->and((bool) DB::table('module_installations')->where('module_name', 'Announcement')->value('enabled'))->toBeFalse()
            ->and((bool) DB::table('module_installations')->where('module_name', 'Announcement')->value('restart_required'))->toBeTrue()
            ->and(Cache::has('admin-navigation-routes:v2'))->toBeFalse();
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

it('keeps installed compatibility separate from a newer incompatible catalog release', function (): void {
    $url = 'https://registry.example.test/newer-incompatible.json';
    $data = json_decode(File::get(base_path('Modules/Announcement/module.json')), true, 512, JSON_THROW_ON_ERROR);
    $data['version'] = '99.0.0';
    $data['requires'] = ['core' => '>=99.0.0'];
    $data['versions'] = [[
        'version' => '99.0.0', 'asset_url' => 'https://example.test/announcement.zip', 'sha256' => str_repeat('a', 64),
        'requires' => ['core' => '>=99.0.0'],
    ]];
    config([
        'modules-marketplace.enabled' => true,
        'modules-marketplace.registry_url' => $url,
        'modules-marketplace.require_signature' => false,
    ]);
    Illuminate\Support\Facades\Http::preventStrayRequests();
    Illuminate\Support\Facades\Http::fake([
        $url => Illuminate\Support\Facades\Http::response(['schema' => 1, 'modules' => [$data]]),
        'https://api.github.com/repos/yukazakiri/koakademy/*' => Illuminate\Support\Facades\Http::response([]),
    ]);
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->assignRole(UserRole::Admin);

    actingAs($user)->get(portalUrlForAdministrators('/administrators/module-marketplace'))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('marketplace.modules.0.name', 'Announcement')
        ->where('marketplace.modules.0.compatible', true)
        ->where('marketplace.modules.0.update_available', true)
        ->where('marketplace.modules.0.update_compatible', false));
});
