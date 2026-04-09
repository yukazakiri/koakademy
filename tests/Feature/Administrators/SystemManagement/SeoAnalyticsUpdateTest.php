<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutMiddleware;

it('updates analytics configuration from the analytics system management form', function (): void {
    $settings = GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    foreach (['View:SystemManagementAnalytics', 'Update:SystemManagementAnalytics'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementAnalytics', 'Update:SystemManagementAnalytics']);
    withoutMiddleware();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/analytics'), [
            'analytics_enabled' => true,
            'analytics_provider' => 'google',
            'analytics_script' => '',
            'analytics_settings' => [
                'google_measurement_id' => 'G-KOATEST01',
            ],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings->refresh();

    expect($settings->analytics_enabled)->toBeTrue()
        ->and($settings->analytics_provider)->toBe('google')
        ->and($settings->analytics_script)->toBeNull()
        ->and($settings->analytics_settings)->toMatchArray([
            'google_measurement_id' => 'G-KOATEST01',
        ])
        ->and($settings->google_analytics_id)->toBe('G-KOATEST01');
});
