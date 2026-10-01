<?php

declare(strict_types=1);

use App\Enums\UserRole;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Permissions each administrative desk is gated on, and the roles that must hold them.
 *
 * These assertions fail loudly if a permission is renamed or an include token silently
 * stops matching, which is how the finance section previously 403'd for every role.
 */
dataset('desk permission gates', [
    'executive' => [[
        UserRole::President->value,
        UserRole::VicePresident->value,
        UserRole::Dean->value,
        UserRole::AssociateDean->value,
    ], ['ViewAny:Department', 'ViewAny:Student', 'generate_reports']],

    'academic' => [[
        UserRole::DepartmentHead->value,
        UserRole::ProgramChair->value,
    ], ['ViewAny:Student', 'ViewAny:Course', 'ViewAny:Classes', 'ViewAny:Faculty']],

    'registrar' => [[
        UserRole::Registrar->value,
        UserRole::AssistantRegistrar->value,
    ], ['ViewAny:StudentEnrollment', 'view_clearance']],

    'accounting' => [[
        UserRole::Cashier->value,
        UserRole::AccountingOfficer->value,
        UserRole::BursarOfficer->value,
    ], ['view_tuition_fees', 'view_payments', 'process_payments', 'View:Cashier']],

    'hr' => [[
        UserRole::HRManager->value,
    ], ['ViewAny:User', 'ViewAny:Faculty', 'ViewAny:Department']],

    'student affairs' => [[
        UserRole::StudentAffairsOfficer->value,
        UserRole::GuidanceCounselor->value,
    ], ['ViewAny:Student', 'view_clearance']],

    'it' => [[
        UserRole::ITSupport->value,
    ], ['View:InventoryProduct', 'ViewAny:GeneralSetting']],
]);

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

it('grants every role the permissions its desk requires', function (array $roles, array $permissions): void {
    foreach ($roles as $roleName) {
        $role = Role::where('name', $roleName)->first();

        expect($role)->not->toBeNull();

        $granted = $role->permissions->pluck('name')->all();
        $missing = array_values(array_diff($permissions, $granted));

        expect($missing)->toBe([]);
    }
})->with('desk permission gates');

it('creates the View:Cashier permission the finance section authorizes against', function (): void {
    expect(Permission::where('name', 'View:Cashier')->exists())->toBeTrue();
});

it('gives finance roles the View:Cashier permission used by the finance nav and controller', function (): void {
    foreach ([UserRole::Cashier, UserRole::AccountingOfficer, UserRole::BursarOfficer] as $role) {
        $permissions = Role::where('name', $role->value)->first()?->permissions->pluck('name')->all() ?? [];

        expect($permissions)->toContain('View:Cashier');
    }
});

it('resolves every include token used by a role to at least one real permission', function (): void {
    $permissions = Permission::pluck('name')->toArray();
    $seeder = app(RolesSeeder::class);

    $roleMap = (new ReflectionMethod($seeder, 'getRolePermissionMap'))->invoke($seeder);

    $unresolved = [];

    foreach ($roleMap as $roleName => $_) {
        $method = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', (string) $roleName))).'Permissions';

        if (! method_exists($seeder, $method)) {
            continue;
        }

        $parameters = (new ReflectionMethod($seeder, $method))->getParameters();

        $includes = [];

        foreach ($parameters as $parameter) {
            if ($parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() !== []) {
                $includes = $parameter->getDefaultValue();
            }
        }

        foreach (RolesSeeder::unresolvedTokens($permissions, $includes) as $token) {
            $unresolved[] = "{$roleName}: {$token}";
        }
    }

    expect($unresolved)->toBe([]);
});

it('reports an unresolved token so a typo cannot silently strip access', function (): void {
    $permissions = ['ViewAny:Student', 'ViewAny:Course'];

    expect(RolesSeeder::unresolvedTokens($permissions, ['ViewAny:Student']))->toBe([]);
    expect(RolesSeeder::unresolvedTokens($permissions, ['ViewDashbord']))->toBe(['ViewDashbord']);
});

it('excludes destroy permissions from the dean role', function (): void {
    $permissions = Role::where('name', UserRole::Dean->value)->first()?->permissions->pluck('name')->all() ?? [];

    expect($permissions)->toContain('ViewAny:Student');
    expect($permissions)->not->toContain('Delete:Student');
});

it('keeps the audit log nav gate on a permission that actually exists', function (): void {
    expect(Permission::where('name', 'ViewAny:Activity')->exists())->toBeTrue();

    $nav = File::get(resource_path('js/config/admin-routes.tsx'));

    expect($nav)->not->toContain('View:LogTable');
});
