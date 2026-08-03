<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Support\SystemManagementPermissions;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

it('shares newsletter settings visibility only with authorized administrators', function (?string $permissionName, bool $expected): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);

    if ($permissionName !== null) {
        Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($permissionName);
    }

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/settings'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('profile', false)
            ->whereType('can_view_newsletter_settings', 'boolean')
            ->where('can_view_newsletter_settings', $expected));
})->with([
    'newsletter viewer' => [SystemManagementPermissions::viewPermission('newsletter'), true],
    'newsletter updater' => [SystemManagementPermissions::updatePermission('newsletter'), true],
    'administrator without newsletter access' => [null, false],
]);
