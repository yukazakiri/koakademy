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
        'Update:ClassEnrollment',
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
            ->map(fn (string $name): Spatie\Permission\Contracts\Permission => Permission::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->authorizedRoleNames())
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
            ->whereIn('name', $this->authorizedRoleNames())
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

    /**
     * Get the list of system role names authorized for enrollment updates.
     *
     * @return list<string>
     */
    private function authorizedRoleNames(): array
    {
        return collect([
            UserRole::Developer,
            UserRole::SuperAdmin,
            UserRole::Admin,
            UserRole::Registrar,
            UserRole::AssistantRegistrar,
        ])->flatMap(fn (UserRole $role): array => array_filter([
            $role->value,
            $role->getLabel(),
        ]))->unique()
            ->values()
            ->all();
    }
};
