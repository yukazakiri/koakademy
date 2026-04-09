<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function grantApiManagementPermission(User $user): void
{
    foreach (['View:SystemManagementApi', 'Update:SystemManagementApi'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementApi', 'Update:SystemManagementApi']);
}

it('saves api management configuration to more_configs', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantApiManagementPermission($user);

    $settings = GeneralSetting::query()->first();
    if (! $settings) {
        $settings = GeneralSetting::create(['site_name' => 'Test']);
    }

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/api'), [
            'public_api_enabled' => true,
            'public_settings_enabled' => true,
            'public_settings_fields' => ['site_name', 'theme_color', 'school_portal_url'],
            'site_name' => 'Updated Public Site',
            'site_description' => 'Public description',
            'theme_color' => '#225588',
            'support_email' => 'public@example.com',
            'support_phone' => '+63 900 000 0000',
            'social_network' => ['facebook' => 'https://facebook.com/example'],
            'school_portal_url' => 'https://public-portal.example.com',
            'school_portal_enabled' => true,
            'online_enrollment_enabled' => true,
            'school_portal_maintenance' => false,
            'school_portal_title' => 'Public Portal',
            'school_portal_description' => 'Public portal description',
        ])
        ->assertRedirect();

    $settings->refresh();

    expect(data_get($settings->more_configs, 'api_management.public_api_enabled'))->toBeTrue()
        ->and(data_get($settings->more_configs, 'api_management.public_settings_enabled'))->toBeTrue()
        ->and(data_get($settings->more_configs, 'api_management.public_settings_fields'))->toBe([
            'site_name',
            'theme_color',
            'school_portal_url',
        ])
        ->and($settings->site_name)->toBe('Updated Public Site')
        ->and($settings->theme_color)->toBe('#225588')
        ->and($settings->school_portal_url)->toBe('https://public-portal.example.com');
});

it('rejects unsupported public api fields', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantApiManagementPermission($user);

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/api'), [
            'public_api_enabled' => true,
            'public_settings_enabled' => true,
            'public_settings_fields' => ['site_name', 'mail_password'],
        ])
        ->assertSessionHasErrors('public_settings_fields.1');
});
