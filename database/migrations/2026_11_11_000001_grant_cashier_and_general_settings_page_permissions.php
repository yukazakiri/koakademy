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

        // Keep grants because they may have existed before this migration.

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Get the role names authorized to access general settings.
     *
     * @return list<string>
     */
    private function settingsRoleNames(): array
    {
        return [
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Developer->value,
        ];
    }

    /**
     * Get the role names authorized to access cashier workflows.
     *
     * @return list<string>
     */
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
