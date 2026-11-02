<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = collect(['View:SystemManagementStudentConfiguration', 'Update:SystemManagementStudentConfiguration'])
            ->map(fn (string $name): Permission => Permission::findOrCreate($name, 'web'));
        $roles = collect([UserRole::Developer, UserRole::Admin, UserRole::SuperAdmin])
            ->flatMap(fn (UserRole $role): array => [$role->value, $role->getLabel()])->unique();
        Role::query()->whereIn('name', $roles)->get()->each(fn (Role $role) => $role->givePermissionTo($permissions));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', ['View:SystemManagementStudentConfiguration', 'Update:SystemManagementStudentConfiguration'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
