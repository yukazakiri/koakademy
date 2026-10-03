<?php

declare(strict_types=1);

use App\Dashboards\Contracts\Dashboard;
use App\Dashboards\DashboardContext;
use App\Dashboards\DashboardRegistry;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Faculty;
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

it('applies the requested trend range and reflects it in the payload', function (): void {
    $this->actingAs(deskUser(UserRole::Dean));

    $this->get('/administrators/desks/executive?range=months')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('context.range', 'months')
            ->has('context.range_label'),
        );

    $this->get('/administrators/desks/executive')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('context.range', 'year'));
});

it('caches each trend range separately', function (): void {
    $contexts = [
        DashboardContext::for('year'),
        DashboardContext::for('months'),
    ];

    expect($contexts[0]->cacheKey())->not->toBe($contexts[1]->cacheKey());
    expect($contexts[0]->rangeLabel())->not->toBe($contexts[1]->rangeLabel());
});

it('renders every desk a super administrator can open', function (string $desk): void {
    $admin = User::factory()->create(['role' => UserRole::Developer]);
    $admin->syncPermissions([]);

    $this->actingAs($admin);

    $this->get("/administrators/desks/{$desk}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('administrators/desks/show')->where('desk.id', $desk));
})->with([
    'executive',
    'registrar',
    'accounting',
    'academic',
    'hr',
    'student-affairs',
    'it-admin',
]);

it('sends the switcher options to the page', function (): void {
    $this->actingAs(deskUser(UserRole::Cashier));

    $this->get('/administrators/desks/accounting')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/desks/show')
            ->has('desks')
            ->has('kpis')
            ->has('queues'),
        );
});

it('renders a desk with no queues or tables without error', function (): void {
    $this->actingAs(deskUser(UserRole::MaintenanceStaff));

    $this->get('/administrators/desks/it-admin')->assertOk();
});

it('sends the whole desk in one response', function (): void {
    $this->actingAs(deskUser(UserRole::Dean));

    $response = $this->get('/administrators/desks/executive');

    $page = json_decode(
        preg_replace('/^.*?<script data-page="app" type="application\/json">(.*?)<\/script>.*$/s', '$1', $response->getContent()) ?: '{}',
        true,
    );

    // Everything the desk renders arrives together. Deferring was removed: the payload is
    // under 2.3 KB and the registry caches it for five minutes, so a second round trip cost
    // more than the bytes it avoided shipping twice.
    expect($page['props'])->toHaveKeys(['kpis', 'queues', 'trends', 'tables', 'desk', 'context', 'desks']);

    // The desk's own defer group is gone. `admin-shell` still defers from the shared
    // middleware, which is unrelated to the desk payload.
    expect($page['deferredProps'])->not->toHaveKey('desk-secondary');
});

it('serves any single prop on a partial reload', function (): void {
    $user = deskUser(UserRole::Dean);

    $this->actingAs($user);
    $full = json_decode(
        preg_replace('/^.*?<script data-page="app" type="application\/json">(.*?)<\/script>.*$/s', '$1', $this->get('/administrators/desks/executive')->getContent()) ?: '{}',
        true,
    );

    $partial = $this->get('/administrators/desks/executive', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $full['version'],
        'X-Inertia-Partial-Data' => 'trends,tables',
        'X-Inertia-Partial-Component' => 'administrators/desks/show',
    ])->assertOk();

    $props = json_decode($partial->getContent(), true)['props'];

    expect(array_keys($props))->toEqualCanonicalizing(['errors', 'trends', 'tables']);
    expect($props['trends'])->toBeArray();
    expect($props['tables'])->toBeArray();
});

it('still authorizes a desk on a partial reload', function (): void {
    $user = deskUser(UserRole::Cashier);

    $this->actingAs($user);
    $full = json_decode(
        preg_replace('/^.*?<script data-page="app" type="application\/json">(.*?)<\/script>.*$/s', '$1', $this->get('/administrators/desks/accounting')->getContent()) ?: '{}',
        true,
    );

    $this->get('/administrators/desks/accounting', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $full['version'],
        'X-Inertia-Partial-Data' => 'trends,tables',
        'X-Inertia-Partial-Component' => 'administrators/desks/show',
    ])->assertOk();

    // Same route, a desk this user cannot see.
    $this->get('/administrators/desks/executive', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $full['version'],
        'X-Inertia-Partial-Data' => 'trends,tables',
        'X-Inertia-Partial-Component' => 'administrators/desks/show',
    ])->assertForbidden();
});

it('exposes each desk to the sidebar with a reachable link', function (string $roleName): void {
    $user = deskUser(UserRole::from($roleName));

    $nav = app(DashboardRegistry::class)->navigationFor($user);

    expect($nav)->not->toBeEmpty();

    foreach ($nav as $entry) {
        expect($entry)->toHaveKeys(['id', 'title', 'link', 'section', 'icon', 'description']);

        // Desks group under their own sidebar section so they are not mixed in with the
        // institution-wide dashboards they are not.
        expect($entry['section'])->toBe('desks');

        // Titles are suffixed so the sidebar reads as "Executive Desk", not a bare role name.
        expect($entry['title'])->toEndWith(' Desk');

        // A desk must never appear in navigation for someone who would get a 403.
        $this->actingAs($user)->get($entry['link'])->assertOk();
    }
})->with([
    UserRole::President->value,
    UserRole::Dean->value,
    UserRole::DepartmentHead->value,
    UserRole::Registrar->value,
    UserRole::Cashier->value,
    UserRole::HRManager->value,
    UserRole::GuidanceCounselor->value,
    UserRole::MaintenanceStaff->value,
]);

it('gives every desk a unique navigation id', function (): void {
    $ids = array_column(app(DashboardRegistry::class)->navigationFor(deskUser(UserRole::Dean)), 'id');

    expect($ids)->toBe(array_unique($ids));
});

it('never lists a desk the role cannot open', function (): void {
    $registry = app(DashboardRegistry::class);

    foreach (UserRole::cases() as $role) {
        $user = deskUser($role);

        foreach ($registry->navigationFor($user) as $entry) {
            expect($registry->resolve($user, str_replace('admin-desk-', '', $entry['id'])))
                ->not->toBeNull();
        }
    }
});

it('shares desk navigation through the deferred admin-shell group', function (): void {
    $user = deskUser(UserRole::Cashier);

    $this->actingAs($user);
    $full = json_decode(
        preg_replace('/^.*?<script data-page="app" type="application\/json">(.*?)<\/script>.*$/s', '$1', $this->get('/administrators/departments')->getContent()) ?: '{}',
        true,
    );

    // Deferred alongside the rest of the shell, so the sidebar is not blocked on it.
    expect($full['deferredProps']['admin-shell'] ?? [])->toContain('deskRoutes');

    $partial = $this->get('/administrators/departments', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $full['version'],
        'X-Inertia-Partial-Data' => 'deskRoutes',
        'X-Inertia-Partial-Component' => 'administrators/departments/index',
    ])->assertOk();

    $desks = json_decode($partial->getContent(), true)['props']['deskRoutes'];

    expect(array_column($desks, 'id'))->toContain('admin-desk-accounting');
});

it('pins a department head to their own department regardless of the query string', function (): void {
    $school = School::factory()->create();
    $mine = Department::factory()->create(['school_id' => $school->id, 'code' => 'MINE']);
    $other = Department::factory()->create(['school_id' => $school->id, 'code' => 'OTHER']);

    $user = deskUser(UserRole::DepartmentHead);
    $user->forceFill(['department_id' => $mine->id])->save();

    Faculty::factory()->count(2)->create([
        'school_id' => $school->id,
        'department_id' => $mine->id,
    ]);
    Faculty::factory()->count(5)->create([
        'school_id' => $school->id,
        'department_id' => $other->id,
    ]);

    $this->actingAs($user->fresh());

    // Asking for another department by id must not change the scope.
    $this->get("/administrators/desks/academic?department={$other->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('scope.id', $mine->id)
            ->where('scope.code', 'MINE'),
        );

    // And the faculty KPI must reflect only their own department.
    $this->get("/administrators/desks/academic?department={$other->id}")
        ->assertInertia(fn ($page) => $page
            ->component('administrators/desks/show')
            ->where('kpis.0.value', 2),
        );
});

it('lets institutional roles choose a department by id', function (): void {
    $school = School::factory()->create();
    $dept = Department::factory()->create(['school_id' => $school->id, 'code' => 'CHOSEN']);

    $user = deskUser(UserRole::Dean);

    $this->actingAs($user);

    $this->get("/administrators/desks/academic?department={$dept->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('scope.id', $dept->id));
});

it('ignores a department id for an inactive department', function (): void {
    $school = School::factory()->create();
    $inactive = Department::factory()->create([
        'school_id' => $school->id,
        'code' => 'OLD',
        'is_active' => false,
    ]);

    $this->actingAs(deskUser(UserRole::Dean));

    $this->get("/administrators/desks/academic?department={$inactive->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('scope', null));
});
