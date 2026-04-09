<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('announcement permission migration creates missing permissions and assigns admin roles', function (): void {
    Artisan::call('migrate:rollback', [
        '--path' => 'database/migrations/2026_04_09_220000_ensure_announcement_permissions_exist.php',
        '--realpath' => false,
        '--force' => true,
    ]);

    Permission::query()
        ->whereIn('name', [
            'ViewAny:Announcement',
            'manage_announcements',
            'view_announcements',
        ])
        ->delete();

    foreach ([UserRole::Developer, UserRole::SuperAdmin, UserRole::Admin] as $roleEnum) {
        $role = Role::findOrCreate($roleEnum->value, 'web');
        $role->syncPermissions([]);
    }

    Artisan::call('migrate', [
        '--path' => 'database/migrations/2026_04_09_220000_ensure_announcement_permissions_exist.php',
        '--realpath' => false,
        '--force' => true,
    ]);

    expect(Permission::findByName('ViewAny:Announcement', 'web'))->not->toBeNull();
    expect(Permission::findByName('view_announcements', 'web'))->not->toBeNull();
    expect(Permission::findByName('manage_announcements', 'web'))->not->toBeNull();

    foreach ([UserRole::Developer, UserRole::SuperAdmin, UserRole::Admin] as $roleEnum) {
        $role = Role::findByName($roleEnum->value, 'web');

        expect($role->hasPermissionTo('ViewAny:Announcement'))->toBeTrue();
        expect($role->hasPermissionTo('view_announcements'))->toBeTrue();
        expect($role->hasPermissionTo('manage_announcements'))->toBeTrue();
    }
});
