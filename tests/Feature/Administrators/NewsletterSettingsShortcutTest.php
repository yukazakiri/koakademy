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
            ->where('can_view_newsletter_settings', $expected)
            ->where('newsletter_settings_url', $expected ? '/administrators/settings/newsletter' : null));
})->with([
    'newsletter viewer' => [SystemManagementPermissions::viewPermission('newsletter'), true],
    'newsletter updater' => [SystemManagementPermissions::updatePermission('newsletter'), true],
    'administrator without newsletter access' => [null, false],
]);

it('serves newsletter configuration from the administrator settings route', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $permission = SystemManagementPermissions::viewPermission('newsletter');

    Permission::firstOrCreate([
        'name' => $permission,
        'guard_name' => 'web',
    ]);
    $user->givePermissionTo($permission);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/settings/newsletter'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/newsletter', false)
            ->where('access.active_section', 'newsletter'));
});

it('keeps the global sidebar focused while the settings workspace retains newsletter discovery', function (): void {
    $routes = file_get_contents(resource_path('js/config/admin-routes.tsx')) ?: '';
    $catalog = file_get_contents(resource_path('js/pages/administrators/system-management/settings-catalog.tsx')) ?: '';
    $systemSettingsOffset = mb_strpos($routes, 'id: "admin-system-management"');
    $supportSectionOffset = mb_strpos($routes, '// SUPPORT', $systemSettingsOffset ?: 0);
    $systemSettingsRoute = $systemSettingsOffset === false
        ? ''
        : mb_substr($routes, $systemSettingsOffset, ($supportSectionOffset ?: mb_strlen($routes)) - $systemSettingsOffset);

    expect($routes)
        ->toContain('"View:SystemManagementNewsletter"')
        ->toContain('"Update:SystemManagementNewsletter"')
        ->toContain('"View:SystemManagementFinanceDocuments"')
        ->toContain('"Update:SystemManagementFinanceDocuments"')
        ->toContain('"View:SystemManagementIdentifiers"')
        ->toContain('"Update:SystemManagementIdentifiers"')
        ->not->toContain('title: "Newsletter Marketing"');

    expect($systemSettingsRoute)
        ->toContain('title: "Settings"')
        ->toContain('link: "/administrators/system-management"')
        ->not->toContain('subs:');

    expect($catalog)
        ->toContain('label: "Newsletter"')
        ->toContain('href: "/administrators/system-management/newsletter"');
});
