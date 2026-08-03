<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Support\SystemManagementPermissions;
use Spatie\Permission\Models\Role;

it('backfills newsletter permissions for existing system administrator roles', function (string $roleName): void {
    $role = Role::firstOrCreate([
        'name' => $roleName,
        'guard_name' => 'web',
    ]);

    $migration = require database_path('migrations/2026_08_03_164859_grant_newsletter_system_management_permissions.php');
    $migration->up();

    $role = $role->fresh();

    expect($role->hasPermissionTo(SystemManagementPermissions::viewPermission('newsletter')))->toBeTrue()
        ->and($role->hasPermissionTo(SystemManagementPermissions::updatePermission('newsletter')))->toBeTrue();
})->with([
    'administrator' => UserRole::Admin->value,
    'super administrator' => UserRole::SuperAdmin->value,
    'developer' => UserRole::Developer->value,
    'legacy administrator label' => UserRole::Admin->getLabel(),
]);
