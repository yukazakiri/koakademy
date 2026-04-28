<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\User;
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

it('uses the paginator total as the global student total on the unfiltered students index', function (): void {
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
                ->where('stats.total_students', 21)
                ->where('adminSidebarCounts.students', 21)
            );
    });

    expect(studentAggregateQueries($queries))->toBe([
        'select count(*) as aggregate from students where students.deleted_at is null',
    ]);
});

it('keeps the global student total when students index filters are active', function (): void {
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
                ->where('adminSidebarCounts.students', 5)
            );
    });

    $studentAggregateQueries = studentAggregateQueries($queries);

    $globalStudentAggregateQuery = 'select count(*) as aggregate from students where students.deleted_at is null';

    expect(array_values(array_filter(
        $studentAggregateQueries,
        static fn (string $query): bool => $query === $globalStudentAggregateQuery,
    )))->toHaveCount(1);

    $filteredPaginatorAggregateQueries = array_values(array_filter(
        $studentAggregateQueries,
        static fn (string $query): bool => $query !== $globalStudentAggregateQuery,
    ));

    expect($filteredPaginatorAggregateQueries)->toHaveCount(1)
        ->and($filteredPaginatorAggregateQueries[0])->toContain('student_type = ?');
});

it('keeps the global student total when filters return no matching students', function (): void {
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
            ->where('adminSidebarCounts.students', 4)
        );
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
    $sql = mb_strtolower($sql);
    $sql = str_replace(['"', '`', '[', ']'], '', $sql);
    $sql = preg_replace('/\s+/', ' ', mb_trim($sql));

    return is_string($sql) ? $sql : mb_trim(mb_strtolower($sql));
}

/**
 * @param  array<int, string>  $queries
 * @return array<int, string>
 */
function studentAggregateQueries(array $queries): array
{
    return array_values(array_filter(
        $queries,
        static fn (string $query): bool => str_contains($query, 'count(*) as aggregate') && str_contains($query, 'students'),
    ));
}
