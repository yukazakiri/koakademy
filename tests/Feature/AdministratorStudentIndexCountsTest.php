<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\Student;
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

it('returns the complete student dataset for local pagination on the unfiltered students index', function (): void {
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
                ->has('students.data', 21)
                ->where('students.total', 21)
                ->where('stats.total_students', 21)
            );
    });

    expect(studentAggregateQueries($queries))->toBe([
        'select count(*) as aggregate from students where students.deleted_at is null',
    ]);
});

it('keeps the global student total when client-side filters are active', function (): void {
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
                ->has('students.data', 5)
                ->where('students.total', 5)
                ->where('stats.total_students', 5)
            );
    });

    $studentAggregateQueries = studentAggregateQueries($queries);

    $globalStudentAggregateQuery = 'select count(*) as aggregate from students where students.deleted_at is null';

    expect(array_values(array_filter(
        $studentAggregateQueries,
        static fn (string $query): bool => $query === $globalStudentAggregateQuery,
    )))->toHaveCount(1);

    expect($studentAggregateQueries)->toBe([$globalStudentAggregateQuery]);
});

it('keeps the complete dataset when client-side filters return no matching students', function (): void {
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
            ->has('students.data', 4)
            ->where('students.total', 4)
            ->where('stats.total_students', 4)
        );
});

it('loads the complete dataset without sqlite-specific query errors when search is supplied', function (string $search): void {
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
            ->has('students.data', 2)
            ->where('students.data', fn ($students): bool => $students->contains(
                fn (array $student): bool => $student['student_id'] === 20240001
            ))
        );
})->with([
    'student id' => ['20240001'],
    'full name' => ['jane doe'],
    'last name first' => ['DOE, JANE'],
]);

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
