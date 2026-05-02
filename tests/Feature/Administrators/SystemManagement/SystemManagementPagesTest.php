<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Support\SystemManagementPermissions;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

function grantSystemManagementPermissions(User $user, array $sections): void
{
    foreach ($sections as $section) {
        $viewPermission = SystemManagementPermissions::viewPermission($section);

        Permission::firstOrCreate([
            'name' => $viewPermission,
            'guard_name' => 'web',
        ]);

        $user->givePermissionTo($viewPermission);

        $updatePermission = SystemManagementPermissions::updatePermission($section);

        if ($updatePermission !== null) {
            Permission::firstOrCreate([
                'name' => $updatePermission,
                'guard_name' => 'web',
            ]);

            $user->givePermissionTo($updatePermission);
        }
    }
}

it('renders all refactored system management pages', function (string $url, string $component): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, SystemManagementPermissions::sectionKeys());

    actingAs($user)
        ->get(portalUrlForAdministrators($url))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component($component, false)
            ->has('user')
            ->has('general_settings')
            ->has('active_school')
            ->has('schools')
            ->has('socialite_config')
            ->has('mail_config')
            ->has('analytics')
            ->has('branding')
            ->has('enrollment_pipeline')
            ->has('enrollment_stats')
            ->has('available_roles')
            ->has('notification_channels')
            ->has('access.sections'));
})->with([
    'school page' => ['/administrators/system-management/school', 'administrators/system-management/school'],
    'pipeline page' => ['/administrators/system-management/enrollment-pipeline', 'administrators/system-management/enrollment-pipeline'],
    'seo page' => ['/administrators/system-management/seo', 'administrators/system-management/seo'],
    'analytics page' => ['/administrators/system-management/analytics', 'administrators/system-management/analytics'],
    'brand page' => ['/administrators/system-management/brand', 'administrators/system-management/brand'],
    'socialite page' => ['/administrators/system-management/socialite', 'administrators/system-management/socialite'],
    'mail page' => ['/administrators/system-management/mail', 'administrators/system-management/mail'],
    'notifications page' => ['/administrators/system-management/notifications', 'administrators/system-management/notifications'],
    'pulse page' => ['/administrators/system-management/pulse', 'administrators/system-management/pulse'],
]);

it('redirects the system management index to the first accessible section', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['school']);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management'))
        ->assertRedirect(portalUrlForAdministrators('/administrators/system-management/school'));
});
