<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function createSystemManagementPermission(string $permission): void
{
    Permission::firstOrCreate([
        'name' => $permission,
        'guard_name' => 'web',
    ]);
}

it('forbids access to a system management page without the matching permission', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::ITSupport,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/brand'))
        ->assertForbidden();
});

it('redirects the index route to the first section the user can access', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::ITSupport,
    ]);

    createSystemManagementPermission('View:SystemManagementBrand');
    $user->givePermissionTo('View:SystemManagementBrand');

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management'))
        ->assertRedirect(portalUrlForAdministrators('/administrators/system-management/brand'));
});

it('exposes only permitted sections in the system management access payload', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::ITSupport,
    ]);

    createSystemManagementPermission('View:SystemManagementBrand');
    createSystemManagementPermission('Update:SystemManagementBrand');
    createSystemManagementPermission('View:SystemManagementPulse');

    $user->givePermissionTo([
        'View:SystemManagementBrand',
        'Update:SystemManagementBrand',
        'View:SystemManagementPulse',
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/brand'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('access.sections.brand.can_view', true)
            ->where('access.sections.brand.can_update', true)
            ->where('access.sections.pulse.can_view', true)
            ->where('access.sections.pulse.can_update', false)
            ->where('access.sections.school.can_view', false));
});

it('forbids updates when the user only has view access for a section', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::ITSupport,
    ]);

    createSystemManagementPermission('View:SystemManagementApi');
    $user->givePermissionTo('View:SystemManagementApi');

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/api'), [
            'public_api_enabled' => true,
            'public_settings_enabled' => true,
            'public_settings_fields' => ['site_name'],
            'site_name' => 'Read Only',
            'site_description' => 'Read only',
            'theme_color' => '#111111',
            'support_email' => 'readonly@example.com',
            'support_phone' => '+63 900 000 0000',
            'social_network' => ['facebook' => 'https://facebook.com/example'],
            'school_portal_url' => 'https://portal.example.com',
            'school_portal_enabled' => true,
            'online_enrollment_enabled' => true,
            'school_portal_maintenance' => false,
            'school_portal_title' => 'Portal',
            'school_portal_description' => 'Portal description',
        ])
        ->assertForbidden();
});

it('allows updates when the user has the matching update permission', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::ITSupport,
    ]);

    foreach (['View:SystemManagementApi', 'Update:SystemManagementApi'] as $permission) {
        createSystemManagementPermission($permission);
    }

    $user->givePermissionTo(['View:SystemManagementApi', 'Update:SystemManagementApi']);

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/api'), [
            'public_api_enabled' => true,
            'public_settings_enabled' => true,
            'public_settings_fields' => ['site_name'],
            'site_name' => 'Permitted Update',
            'site_description' => 'Allowed',
            'theme_color' => '#111111',
            'support_email' => 'allowed@example.com',
            'support_phone' => '+63 900 000 0000',
            'social_network' => ['facebook' => 'https://facebook.com/example'],
            'school_portal_url' => 'https://portal.example.com',
            'school_portal_enabled' => true,
            'online_enrollment_enabled' => true,
            'school_portal_maintenance' => false,
            'school_portal_title' => 'Portal',
            'school_portal_description' => 'Portal description',
        ])
        ->assertRedirect();
});
