<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Support\SystemManagementPermissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $permissions = collect([
            SystemManagementPermissions::viewPermission('faculty_fields'),
            SystemManagementPermissions::updatePermission('faculty_fields'),
        ])->filter()->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));
        Role::query()->whereIn('name', $this->roleNames())->get()->each(
            fn (Role $role) => $role->givePermissionTo($permissions),
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }
        $names = array_filter([
            SystemManagementPermissions::viewPermission('faculty_fields'),
            SystemManagementPermissions::updatePermission('faculty_fields'),
        ]);
        Role::query()->whereIn('name', $this->roleNames())->get()->each(function (Role $role) use ($names): void {
            foreach ($names as $name) {
                if ($role->hasPermissionTo($name)) {
                    $role->revokePermissionTo($name);
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function roleNames(): array
    {
        return collect([UserRole::Developer, UserRole::Admin, UserRole::SuperAdmin])
            ->flatMap(fn (UserRole $role): array => array_filter([$role->value, $role->getLabel()]))
            ->unique()->values()->all();
    }
};
