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
    private const array PERMISSIONS = [
        'Assign:Role',
        'ViewAny:Permission',
        'View:Permission',
        'Create:Permission',
        'Update:Permission',
        'Delete:Permission',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->systemRoleNames())
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->systemRoleNames())
            ->get()
            ->each(function (Role $role): void {
                foreach (self::PERMISSIONS as $permissionName) {
                    if ($role->hasPermissionTo($permissionName)) {
                        $role->revokePermissionTo($permissionName);
                    }
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function systemRoleNames(): array
    {
        return collect([
            UserRole::Developer,
            UserRole::Admin,
            UserRole::SuperAdmin,
        ])->flatMap(fn (UserRole $role): array => array_filter([
            $role->value,
            $role->getLabel(),
        ]))->unique()
            ->values()
            ->all();
    }
};
