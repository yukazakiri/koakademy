<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentStatusRecord;
use App\Models\User;
use App\Support\AdministratorSidebarCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    Cache::flush();
});

it('returns paginated students on the unfiltered students index', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Student::factory()->count(21)->create();

    $queries = captureExecutedSql(function () use ($user): void {
        actingAs($user)
            ->get(portalUrlForAdministrators('/administrators/students'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('administrators/students/index', false)
                ->has('students.data', 20)
                ->where('students.total', 21)
                ->where('students.current_page', 1)
                ->where('students.last_page', 2)
                ->where('stats.total_students', 21)
            );

        expect(app(AdministratorSidebarCounts::class)->resolve(app('request'))['students'])->toBe(21);
    });

    expect(studentAggregateQueries($queries))->toBe([
        'select count(*) as aggregate from students where students.deleted_at is null',
    ]);
});

it('keeps the global student total when filters are active', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Student::factory()->count(3)->create([
        'student_type' => StudentType::College->value,
    ]);
    Student::factory()->count(2)->create([
        'student_type' => StudentType::SeniorHighSchool->value,
    ]);

    $queries = captureExecutedSql(function () use ($user): void {
        actingAs($user)
            ->get(portalUrlForAdministrators('/administrators/students?type=college'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('administrators/students/index', false)
                ->has('students.data', 3)
                ->where('students.total', 3)
                ->where('stats.total_students', 5)
            );

        expect(app(AdministratorSidebarCounts::class)->resolve(app('request'))['students'])->toBe(5);
    });

    $studentAggregateQueries = studentAggregateQueries($queries);

    $globalStudentAggregateQuery = 'select count(*) as aggregate from students where students.deleted_at is null';

    expect(array_values(array_filter(
        $studentAggregateQueries,
        static fn (string $query): bool => $query === $globalStudentAggregateQuery,
    )))->toHaveCount(1);
});

it('returns empty dataset when filters return no matching students', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Student::factory()->count(4)->create([
        'student_type' => StudentType::SeniorHighSchool->value,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students?type=college'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 0)
            ->where('students.total', 0)
            ->where('stats.total_students', 4)
        );
});

it('filters the dataset without sqlite-specific query errors when search is supplied', function (string $search): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Student::factory()->create([
        'student_id' => 20240001,
        'first_name' => 'Jane',
        'last_name' => 'Doe',
    ]);

    Student::factory()->create([
        'student_id' => 20240002,
        'first_name' => 'Miguel',
        'last_name' => 'Santos',
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students?search='.urlencode($search)))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 1)
            ->where('students.total', 1)
            ->where('students.data.0.student_id', 20240001)
        );
})->with([
    'student id' => ['20240001'],
    'full name' => ['jane doe'],
    'last name first' => ['DOE, JANE'],
]);

it('keeps initial Inertia students deferred while resolving global stats and options once', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create();
    StudentStatusRecord::query()->create([
        'student_id' => $student->id,
        'school_id' => $student->school_id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'status' => StudentStatus::Enrolled->value,
    ]);

    $queries = captureExecutedSql(function () use ($user): void {
        actingAs($user)
            ->get(portalUrlForAdministrators('/administrators/students'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/administrators/students')),
            ])
            ->assertOk()
            ->assertJsonPath('deferredProps.student-directory', ['students'])
            ->assertJsonMissingPath('props.students')
            ->assertJsonPath('props.stats.total_students', 1)
            ->assertJsonPath('props.stats.total_enrolled', 1)
            ->assertJsonPath('props.stats.total_applicants', 0)
            ->assertJsonPath('props.stats.total_graduated', 0)
            ->assertJsonPath('props.user.name', $user->name)
            ->assertJsonStructure(['props' => ['options' => ['courses', 'departments']]]);

        expect(app(AdministratorSidebarCounts::class)->resolve(app('request'))['students'])->toBe(1);
    });

    expect(studentAggregateQueries($queries))->toBe([
        'select count(*) as aggregate from students where students.deleted_at is null',
    ]);
    expect(studentIndexOptionQueries($queries))->toHaveCount(2);
    expect(studentStatusAggregateQueries($queries))->toHaveCount(1);
    expect(array_values(array_filter(
        $queries,
        static fn (string $query): bool => str_starts_with($query, 'select students.id,'),
    )))->toBe([]);
    expect(app('request')->attributes->get('admin_students_global_total'))->toBe(1);
});

it('skips excluded prop queries when loading filtered students through partial or deferred requests', function (string $partialData): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $matchingStudent = Student::factory()->minimal()->create([
        'first_name' => 'DirectoryNeedle',
        'last_name' => 'Match',
    ]);
    Student::factory()->minimal()->create([
        'first_name' => 'Other',
        'last_name' => 'Person',
    ]);

    $queries = captureExecutedSql(function () use ($user, $matchingStudent, $partialData): void {
        actingAs($user)
            ->get(portalUrlForAdministrators('/administrators/students?search=DirectoryNeedle'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/administrators/students')),
                'X-Inertia-Partial-Component' => 'administrators/students/index',
                'X-Inertia-Partial-Data' => $partialData,
            ])
            ->assertOk()
            ->assertJsonPath('component', 'administrators/students/index')
            ->assertJsonCount(1, 'props.students.data')
            ->assertJsonPath('props.students.data.0.id', $matchingStudent->id)
            ->assertJsonPath('props.students.total', 1)
            ->assertJsonMissingPath('props.stats')
            ->assertJsonMissingPath('props.options')
            ->assertJsonMissingPath('props.user')
            ->assertJsonMissingPath('deferredProps');
    });

    $aggregateQueries = studentAggregateQueries($queries);

    expect($aggregateQueries)->toHaveCount(1);
    expect($aggregateQueries[0])->toContain('lower(cast(students.student_id as text)) like lower(?)');
    expect($aggregateQueries)->not->toContain('select count(*) as aggregate from students where students.deleted_at is null');
    expect(studentIndexOptionQueries($queries))->toBe([]);
    expect(studentStatusAggregateQueries($queries))->toBe([]);
    expect(app('request')->attributes->has('admin_students_global_total'))->toBeFalse();
})->with([
    'list update' => ['students,filters'],
    'deferred students load' => ['students'],
]);

it('does not recount students or load options and stats on filters-only partial requests', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    Student::factory()->create();

    $queries = captureExecutedSql(function () use ($user): void {
        actingAs($user)
            ->get(portalUrlForAdministrators('/administrators/students?search=DirectoryNeedle'), [
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/administrators/students')),
                'X-Inertia-Partial-Component' => 'administrators/students/index',
                'X-Inertia-Partial-Data' => 'filters',
            ])
            ->assertOk()
            ->assertJsonPath('props.filters.search', 'DirectoryNeedle')
            ->assertJsonMissingPath('props.students')
            ->assertJsonMissingPath('props.stats')
            ->assertJsonMissingPath('props.options')
            ->assertJsonMissingPath('props.user')
            ->assertJsonMissingPath('deferredProps');
    });

    expect(studentAggregateQueries($queries))->toBe([]);
    expect(studentIndexOptionQueries($queries))->toBe([]);
    expect(studentStatusAggregateQueries($queries))->toBe([]);
});

it('casts cached sidebar student counts to int', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Cache::put('admin_sidebar_counts:all:2024 - 2025:2:students', '7', 60);

    $request = Request::create('/administrators/classes');
    $request->setUserResolver(static fn (): User => $user);

    $counts = app(AdministratorSidebarCounts::class)->resolve($request);

    expect($counts)
        ->not->toBeNull()
        ->and($counts['students'])->toBeInt()->toBe(7);
});

function captureExecutedSql(callable $callback): array
{
    $connection = DB::connection();

    Cache::flush();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    try {
        $callback();

        return array_map(
            static fn (array $query): string => normalizeSqlQuery((string) $query['query']),
            $connection->getQueryLog(),
        );
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
}

function normalizeSqlQuery(string $sql): string
{
    $normalizedSql = mb_strtolower($sql);
    $normalizedSql = str_replace(['"', '`', '[', ']'], '', $normalizedSql);
    $normalizedSql = preg_replace('/\s+/', ' ', mb_trim($normalizedSql));

    if (! is_string($normalizedSql)) {
        return mb_trim(mb_strtolower($sql));
    }

    return $normalizedSql;
}

/**
 * @param  array<int, string>  $queries
 * @return array<int, string>
 */
function studentAggregateQueries(array $queries): array
{
    return array_values(array_filter(
        $queries,
        static fn (string $query): bool => mb_stripos($query, 'count(*) as aggregate') !== false && mb_stripos($query, 'students') !== false,
    ));
}

/**
 * @param  array<int, string>  $queries
 * @return array<int, string>
 */
function studentIndexOptionQueries(array $queries): array
{
    return array_values(array_filter(
        $queries,
        static fn (string $query): bool => str_starts_with($query, 'select id, code, title from courses')
            || str_starts_with($query, 'select id, code, name from departments'),
    ));
}

/**
 * @param  array<int, string>  $queries
 * @return array<int, string>
 */
function studentStatusAggregateQueries(array $queries): array
{
    return array_values(array_filter(
        $queries,
        static fn (string $query): bool => str_contains($query, 'from student_statuses')
            && str_contains($query, 'count(case when status = ? then 1 end)'),
    ));
}
