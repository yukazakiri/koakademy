<?php

declare(strict_types=1);

use App\Dashboards\Contracts\Dashboard;
use App\Dashboards\DashboardContext;
use App\Dashboards\DashboardRegistry;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\School;
use App\Models\User;
use Database\Seeders\RolesSeeder;

/**
 * The desk registry is the single source of truth for which dashboards a user may open, so
 * these assertions guard both the access rules and the payload contract the front-end reads.
 */
beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

/**
 * Create a user with a role, syncing the permissions the seeder granted to that role.
 */
function deskUser(UserRole $role): User
{
    $user = User::factory()->create(['role' => $role]);

    $permissions = Spatie\Permission\Models\Role::where('name', $role->value)->first()?->permissions ?? collect();

    $user->syncPermissions($permissions);

    return $user->fresh();
}

it('registers every desk exactly once with a unique id', function (): void {
    $ids = array_map(fn (Dashboard $desk): string => $desk->id(), app(DashboardRegistry::class)->all());

    expect($ids)->toHaveCount(7);
    expect(array_unique($ids))->toHaveCount(7);
});

it('gives each desk a title and description for the switcher', function (): void {
    foreach (app(DashboardRegistry::class)->all() as $desk) {
        expect($desk->title())->not->toBe('');
        expect($desk->description())->not->toBe('');
    }
});

it('resolves each staff role to at least one desk', function (string $role): void {
    $user = deskUser(UserRole::from($role));

    expect(app(DashboardRegistry::class)->visibleFor($user))
        ->not->toBeEmpty("Role {$role} cannot open any desk.");
})->with([
    UserRole::President->value,
    UserRole::VicePresident->value,
    UserRole::Dean->value,
    UserRole::AssociateDean->value,
    UserRole::DepartmentHead->value,
    UserRole::ProgramChair->value,
    UserRole::Registrar->value,
    UserRole::AssistantRegistrar->value,
    UserRole::Cashier->value,
    UserRole::AccountingOfficer->value,
    UserRole::BursarOfficer->value,
    UserRole::HRManager->value,
    UserRole::StudentAffairsOfficer->value,
    UserRole::GuidanceCounselor->value,
    UserRole::ITSupport->value,
    UserRole::MaintenanceStaff->value,
]);

it('scopes a cashier away from the executive, academic and HR desks', function (): void {
    $ids = array_map(
        fn (Dashboard $desk): string => $desk->id(),
        app(DashboardRegistry::class)->visibleFor(deskUser(UserRole::Cashier)),
    );

    expect($ids)->toContain('accounting');
    expect($ids)->not->toContain('executive');
    expect($ids)->not->toContain('hr');
});

it('scopes an HR manager away from the finance desks', function (): void {
    $ids = array_map(
        fn (Dashboard $desk): string => $desk->id(),
        app(DashboardRegistry::class)->visibleFor(deskUser(UserRole::HRManager)),
    );

    expect($ids)->toContain('hr');
    expect($ids)->not->toContain('accounting');
});

it('lets system administrators open every desk regardless of permissions', function (): void {
    $registry = app(DashboardRegistry::class);

    $admin = User::factory()->create(['role' => UserRole::Developer]);
    $admin->syncPermissions([]);

    expect($registry->visibleFor($admin))->toHaveCount(count($registry->all()));
});

it('returns null when resolving a desk the user may not see', function (): void {
    $registry = app(DashboardRegistry::class);
    $cashier = deskUser(UserRole::Cashier);

    expect($registry->resolve($cashier, 'accounting'))->not->toBeNull();
    expect($registry->resolve($cashier, 'executive'))->toBeNull();
    expect($registry->resolve($cashier, 'does-not-exist'))->toBeNull();
});

it('builds a desk payload with only the documented widget keys', function (): void {
    $registry = app(DashboardRegistry::class);
    $user = deskUser(UserRole::Dean);
    $desk = $registry->resolve($user, 'executive');

    expect($desk)->not->toBeNull();

    $payload = $registry->payloadFor($user, $desk, DashboardContext::for());

    foreach (array_keys($payload) as $key) {
        expect($key)->toBeIn(['kpis', 'queues', 'trends', 'tables', 'activity', 'scope']);
    }

    expect($payload)->toHaveKeys(['kpis', 'queues']);
});

it('gives every kpi the fields the front-end renders', function (): void {
    $registry = app(DashboardRegistry::class);
    $user = deskUser(UserRole::Dean);
    $desk = $registry->resolve($user, 'executive');

    foreach ($registry->payloadFor($user, $desk, DashboardContext::for())['kpis'] as $kpi) {
        expect($kpi)->toHaveKeys(['label', 'value', 'tone']);
        expect($kpi['label'])->toBeString();
        expect($kpi['tone'])->toBeIn(['success', 'warning', 'info', 'neutral']);
    }
});

it('drops a widget the viewer lacks permission for', function (): void {
    $registry = app(DashboardRegistry::class);
    $user = deskUser(UserRole::Dean);
    $desk = $registry->resolve($user, 'executive');
    $context = DashboardContext::for();

    $cache = app(Illuminate\Contracts\Cache\Repository::class);
    $cache->put(
        sprintf('admin_dashboard:executive:%s:%d', $context->cacheKey(), $user->id),
        [
            'kpis' => [],
            'queues' => [],
            'trends' => [],
            'tables' => [
                ['id' => 'allowed', 'permission' => 'ViewAny:Student'],
                ['id' => 'forbidden', 'permission' => 'ViewAny:Role'],
                ['id' => 'ungated', 'title' => 'No permission required'],
            ],
        ],
        300,
    );

    $tables = $registry->payloadFor($user, $desk, $context)['tables'];
    $ids = array_column($tables, 'id');

    expect($ids)->toContain('allowed', 'ungated');
    expect($ids)->not->toContain('forbidden');
});

it('scopes the academic desk to the viewer own department', function (): void {
    $school = School::factory()->create();
    $department = Department::factory()->create(['school_id' => $school->id]);

    $user = deskUser(UserRole::DepartmentHead);
    $user->forceFill(['department_id' => $department->id])->save();

    $context = DashboardContext::for(null, $department->fresh());
    $payload = app(DashboardRegistry::class)->payloadFor(
        $user->fresh(),
        app(DashboardRegistry::class)->resolve($user->fresh(), 'academic'),
        $context,
    );

    expect($payload['scope']['id'])->toBe($department->id);
});

it('gives the academic desk no scope when the viewer has no department', function (): void {
    $registry = app(DashboardRegistry::class);
    $user = deskUser(UserRole::Dean);

    $payload = $registry->payloadFor($user, $registry->resolve($user, 'academic'), DashboardContext::for());

    expect($payload['scope'])->toBeNull();
});

it('caches a desk payload and isolates it per user', function (): void {
    $cache = app(Illuminate\Contracts\Cache\Repository::class);
    $registry = app(DashboardRegistry::class);
    $context = DashboardContext::for();

    $first = deskUser(UserRole::Dean);
    $second = deskUser(UserRole::Dean);

    $desk = $registry->resolve($first, 'executive');
    $registry->payloadFor($first, $desk, $context);

    expect($cache->has(sprintf('admin_dashboard:executive:%s:%d', $context->cacheKey(), $first->id)))->toBeTrue();
    expect($cache->has(sprintf('admin_dashboard:executive:%s:%d', $context->cacheKey(), $second->id)))->toBeFalse();
});

it('builds a school-aware cache key', function (): void {
    $context = DashboardContext::for();

    expect($context->cacheKey())->toContain(':sem'.app(App\Services\GeneralSettingsService::class)->getCurrentSemester());
    expect($context->periodLabel())->toContain('Semester');
});

it('resolves both supported trend ranges', function (): void {
    $months = DashboardContext::for('months');
    $year = DashboardContext::for('year');

    // startOfDay()/endOfDay() padding makes the span slightly over six calendar months.
    expect($months->from->diffInMonths($months->to))->toBeBetween(6, 7);
    expect($months->rangeLabel())->not->toBe($year->rangeLabel());

    // The school-year window must start at or before the trailing-months window.
    expect($year->from->toDateString())->toBeLessThanOrEqual($months->from->toDateString());
});

it('lists switcher options pointing at the desk route', function (): void {
    $options = app(DashboardRegistry::class)->optionsFor(deskUser(UserRole::Cashier));

    expect($options)->not->toBeEmpty();

    foreach ($options as $option) {
        expect($option)->toHaveKeys(['id', 'title', 'description', 'url']);
        expect($option['url'])->toContain('/administrators/desks/');
    }
});

it('redirects a cashier to their own default desk', function (): void {
    $this->actingAs(deskUser(UserRole::Cashier));

    $this->get('/administrators/dashboard')
        ->assertRedirect(route('administrators.desks.show', 'accounting'));
});

it('renders a desk the user is allowed to open', function (): void {
    $this->actingAs(deskUser(UserRole::Cashier));

    $this->get('/administrators/desks/accounting')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/desks/show')
            ->where('desk.id', 'accounting')
            ->has('kpis')
            ->has('queues')
            ->has('context.period_label'),
        );
});

it('forbids a desk the user is not allowed to open', function (): void {
    $this->actingAs(deskUser(UserRole::Cashier));

    $this->get('/administrators/desks/executive')->assertForbidden();
});

it('forbids an unknown desk id', function (): void {
    $this->actingAs(deskUser(UserRole::Cashier));

    $this->get('/administrators/desks/not-a-desk')->assertForbidden();
});
