<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Spatie\Permission\Models\Role;

/**
 * The finance section authorizes against the literal permission strings
 * 'View:Cashier', 'view_tuition_fees' and 'view_payments' — see
 * AdministratorFinanceController::authorizeFinanceAccess(),
 * App\Dashboards\AccountingDesk::canView() and the MCP finance tools.
 *
 * Those strings only exist in the database because RolesSeeder writes them.
 * If a grant is renamed or an include token silently stops matching, every
 * finance route 403s for every role and the section becomes unreachable
 * without any other test noticing.
 *
 * So these assertions drive the real authorization path: they seed roles,
 * hand a user exactly the permissions their role received, then assert the
 * app's own gate opens or stays shut. They deliberately say nothing about
 * how the seeder works — only that the permissions the app gates on are
 * actually reachable by the roles that need them.
 */
beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * A user holding their role's permissions exactly as the seeder granted
 * them, so the assertion exercises the seeded state rather than a
 * hand-built role that could hide a missing grant.
 */
function financeDeskUser(UserRole $role): User
{
    $user = User::factory()->create(['role' => $role]);

    $permissions = Role::where('name', $role->value)->first()?->permissions ?? collect();

    $user->syncPermissions($permissions);

    return $user->fresh();
}

dataset('finance roles', [
    UserRole::Cashier->value,
    UserRole::AccountingOfficer->value,
    UserRole::BursarOfficer->value,
]);

it('lets a finance role open the finance section', function (string $role): void {
    $this->actingAs(financeDeskUser(UserRole::from($role)));

    $this->get(route('administrators.finance.index'))
        ->assertOk();
})->with('finance roles');

it('keeps the accounting desk visible to every finance role', function (string $role): void {
    $ids = array_map(
        fn (App\Dashboards\Contracts\Dashboard $desk): string => $desk->id(),
        app(App\Dashboards\DashboardRegistry::class)->visibleFor(financeDeskUser(UserRole::from($role))),
    );

    expect($ids)->toContain('accounting');
})->with('finance roles');

it('keeps the finance MCP tools reachable for a finance role', function (string $role): void {
    $user = financeDeskUser(UserRole::from($role));

    // Mirrors the guard in GetStatementOfAccountTool and
    // InvestigateStudentFinancesTool, which fall back to 'view_tuition_fees'.
    expect($user->can('View:Cashier') || $user->can('view_tuition_fees'))->toBeTrue();
})->with('finance roles');

it('closes the finance section to roles that do not belong to it', function (string $role): void {
    $this->actingAs(financeDeskUser(UserRole::from($role)));

    $this->get(route('administrators.finance.index'))
        ->assertForbidden();
})->with([
    UserRole::DepartmentHead->value,
    UserRole::GuidanceCounselor->value,
    UserRole::ITSupport->value,
]);

it('keeps the dean able to read students but unable to destroy them', function (): void {
    $permissions = Role::where('name', UserRole::Dean->value)->first()?->permissions->pluck('name')->all() ?? [];

    expect($permissions)->toContain('ViewAny:Student')
        ->and($permissions)->not->toContain('Delete:Student');
});
