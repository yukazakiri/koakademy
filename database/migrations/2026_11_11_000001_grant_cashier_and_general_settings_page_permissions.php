<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Contracts\Permission;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array SETTINGS_PERMISSIONS = [
        'page_GeneralSetting',
        'View:GeneralSettings',
    ];

    private const array CASHIER_PERMISSIONS = [
        'page_Cashier',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $settingsPermissions = collect(self::SETTINGS_PERMISSIONS)
            ->map(fn (string $name): Permission => PermissionModel::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->settingsRoleNames())
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($settingsPermissions));

        $cashierPermissions = collect(self::CASHIER_PERMISSIONS)
            ->map(fn (string $name): Permission => PermissionModel::findOrCreate($name, 'web'));

        Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $this->cashierRoleNames())
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($cashierPermissions));

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

        $allPermissions = [...self::SETTINGS_PERMISSIONS, ...self::CASHIER_PERMISSIONS];

        Role::query()
            ->where('guard_name', 'web')
            ->get()
            ->each(function (Role $role) use ($allPermissions): void {
                foreach ($allPermissions as $permissionName) {
                    if ($role->hasPermissionTo($permissionName)) {
                        $role->revokePermissionTo($permissionName);
                    }
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function settingsRoleNames(): array
    {
        return [
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Developer->value,
        ];
    }

    /** @return list<string> */
    private function cashierRoleNames(): array
    {
        return [
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Developer->value,
            UserRole::Cashier->value,
            UserRole::AccountingOfficer->value,
            UserRole::BursarOfficer->value,
        ];
    }
};
