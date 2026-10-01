<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\AnalyticsProviderInstance;
use App\Models\GeneralSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutMiddleware;

function grantSystemManagementSettingsPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo($permissions);
}

it('updates analytics configuration from the analytics system management form', function (): void {
    $settings = GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementSettingsPermissions($user, ['View:SystemManagementAnalytics', 'Update:SystemManagementAnalytics']);
    withoutMiddleware();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/analytics'), [
            'analytics_enabled' => true,
            'providers' => [
                [
                    'provider' => 'google',
                    'enabled' => true,
                    'settings' => [
                        'measurement_id' => 'G-KOATEST01',
                    ],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings->refresh();

    expect($settings->analytics_enabled)->toBeTrue()
        ->and($settings->google_analytics_id)->toBe('G-KOATEST01');

    $instance = AnalyticsProviderInstance::query()->sole();

    expect($instance->provider)->toBe('google')
        ->and($instance->enabled)->toBeTrue()
        ->and($instance->settings)->toMatchArray(['measurement_id' => 'G-KOATEST01']);
});
