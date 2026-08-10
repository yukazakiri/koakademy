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
    'newsletter page' => ['/administrators/system-management/newsletter', 'administrators/system-management/newsletter'],
    'notifications page' => ['/administrators/system-management/notifications', 'administrators/system-management/notifications'],
    'finance documents page' => ['/administrators/system-management/finance-documents', 'administrators/system-management/finance-documents'],
    'grading page' => ['/administrators/system-management/grading', 'administrators/system-management/grading'],
    'identifiers page' => ['/administrators/system-management/identifiers', 'administrators/system-management/identifiers'],
    'api page' => ['/administrators/system-management/api', 'administrators/system-management/api'],
    'pulse page' => ['/administrators/system-management/pulse', 'administrators/system-management/pulse'],
]);

it('renders a focused settings home with only the sections the administrator can access', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['school', 'identifiers']);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/index', false)
            ->where('access.active_section', null)
            ->where('access.sections.school.can_view', true)
            ->where('access.sections.identifiers.can_view', true)
            ->where('access.sections.brand.can_view', false));
});
