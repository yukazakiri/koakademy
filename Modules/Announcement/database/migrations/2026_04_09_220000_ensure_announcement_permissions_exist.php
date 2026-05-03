<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const string GUARD_NAME = 'web';

    /**
     * @var list<string>
     */
    private const array ANNOUNCEMENT_PERMISSIONS = [
        'ViewAny:Announcement',
        'View:Announcement',
        'Create:Announcement',
        'Update:Announcement',
        'Delete:Announcement',
        'Restore:Announcement',
        'ForceDelete:Announcement',
        'ForceDeleteAny:Announcement',
        'RestoreAny:Announcement',
        'Replicate:Announcement',
        'Reorder:Announcement',
        'view_announcements',
        'manage_announcements',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array ROLE_PERMISSION_MAP = [
        UserRole::Developer->value => self::ANNOUNCEMENT_PERMISSIONS,
        UserRole::SuperAdmin->value => self::ANNOUNCEMENT_PERMISSIONS,
        UserRole::Admin->value => self::ANNOUNCEMENT_PERMISSIONS,
    ];

    public function up(): void
    {
        if (! $this->hasPermissionTables()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ANNOUNCEMENT_PERMISSIONS as $permissionName) {
            Permission::findOrCreate($permissionName, self::GUARD_NAME);
        }

        foreach (self::ROLE_PERMISSION_MAP as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, self::GUARD_NAME);
            $role->givePermissionTo($permissionNames);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! $this->hasPermissionTables()) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLE_PERMISSION_MAP as $roleName => $permissionNames) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', self::GUARD_NAME)
                ->first();

            if ($role === null) {
                continue;
            }

            $role->revokePermissionTo($permissionNames);
        }

        Permission::query()
            ->where('guard_name', self::GUARD_NAME)
            ->whereIn('name', self::ANNOUNCEMENT_PERMISSIONS)
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function hasPermissionTables(): bool
    {
        return Schema::hasTable('permissions')
            && Schema::hasTable('roles')
            && Schema::hasTable('role_has_permissions');
    }
};
