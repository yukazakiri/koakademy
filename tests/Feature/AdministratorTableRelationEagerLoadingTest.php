<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\Faculties\Pages\ListFaculties;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Department;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Every query the given callback runs, lowercased so repeated statements
 * collapse onto one another.
 *
 * @return array<int, string>
 */
function tableRelationQueries(callable $callback): array
{
    $connection = DB::connection();
    $connection->flushQueryLog();
    $connection->enableQueryLog();

    try {
        $callback();

        return array_map(
            static fn (array $query): string => mb_strtolower((string) $query['query']),
            $connection->getQueryLog(),
        );
    } finally {
        $connection->disableQueryLog();
        $connection->flushQueryLog();
    }
}

/**
 * @param  array<int, string>  $queries
 * @return array<int, string>
 */
function tableRelationQueriesMatching(array $queries, string $needle): array
{
    return array_values(array_filter(
        $queries,
        static fn (string $query): bool => str_contains($query, $needle),
    ));
}

/**
 * How many statements touching the given fragment a table page runs.
 */
function tableRelationQueryCount(string $page, string $needle): int
{
    $queries = tableRelationQueries(function () use ($page): void {
        Livewire::test($page)
            ->assertSuccessful();
    });

    return count(tableRelationQueriesMatching($queries, $needle));
}

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    foreach ([
        'ViewAny:Student', 'ViewAny:StudentEnrollment', 'ViewAny:User',
        'ViewAny:Faculty', 'ViewAny:Event', 'ViewAny:Department',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $user->givePermissionTo($permission);
    }

    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
});

it('loads the previous semester clearances of the students page in one query', function (): void {
    $course = Course::factory()->create(['code' => 'BSCS']);

    foreach (range(1, 6) as $ignored) {
        $student = Student::factory()->create(['course_id' => $course->id]);

        StudentClearance::query()->create([
            'student_id' => $student->id,
            'academic_year' => '2024 - 2025',
            'semester' => 1,
            'is_cleared' => true,
        ]);
    }

    expect(tableRelationQueryCount(
        App\Filament\Resources\Students\Pages\ListStudents::class,
        'from "student_clearances"',
    ))->toBe(1);
});

it('resolves the previous semester clearance from the loaded relation without a query', function (): void {
    $student = Student::factory()->create();

    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => true,
    ]);

    $student = Student::query()
        ->with(['clearances' => fn ($query) => $query
            ->where('academic_year', '2024 - 2025')
            ->where('semester', 1)])
        ->findOrFail($student->id);

    $queries = tableRelationQueries(function () use ($student): void {
        expect($student->getPreviousSemesterClearance())->not->toBeNull()
            ->and($student->getPreviousSemesterClearance()?->is_cleared)->toBeTrue();
    });

    expect(tableRelationQueriesMatching($queries, 'from "student_clearances"'))->toBe([]);
});

it('counts the current classes of the faculty page with a single aggregate', function (): void {
    $faculties = Faculty::factory()->count(6)->create();

    foreach ($faculties as $faculty) {
        Classes::factory()->create([
            'faculty_id' => $faculty->id,
            'school_year' => '2024 - 2025',
            'semester' => 2,
        ]);
    }

    $queries = tableRelationQueries(function (): void {
        Livewire::test(ListFaculties::class)
            ->assertSuccessful();
    });

    $classQueries = tableRelationQueriesMatching($queries, 'from "classes"');

    expect($classQueries)->toHaveCount(1)
        ->and($classQueries[0])->not->toContain('faculty_id::text');
});

it('counts only the classes of the current academic period', function (): void {
    $faculty = Faculty::factory()->create();

    Classes::factory()->count(2)->create([
        'faculty_id' => $faculty->id,
        'school_year' => '2024 - 2025',
        'semester' => 2,
    ]);

    Classes::factory()->create([
        'faculty_id' => $faculty->id,
        'school_year' => '2023 - 2024',
        'semester' => 1,
    ]);

    $faculty = Faculty::query()
        ->withCount(['classes as current_classes_count' => fn ($query) => $query->currentAcademicPeriod()])
        ->findOrFail($faculty->id);

    expect($faculty->current_classes_count)->toBe(2);
});

it('searches the faculty table without a malformed relationship expression', function (): void {
    Faculty::factory()->count(3)->create();

    Livewire::test(ListFaculties::class)
        ->searchTable('Prof')
        ->assertSuccessful();

    expect(true)->toBeTrue();
});

it('counts the attendees of the events page with a single aggregate', function (): void {
    foreach (range(1, 6) as $ignored) {
        $event = Event::query()->create([
            'title' => 'Enrollment Orientation',
            'type' => 'event',
            'start_datetime' => now(),
            'end_datetime' => now()->addDay(),
            'created_by' => Auth::id(),
            'max_attendees' => 10,
        ]);

        $attendee = User::factory()->create();

        EventRsvp::query()->create([
            'event_id' => $event->id,
            'user_id' => $attendee->id,
            'response' => 'attending',
            'guest_count' => 2,
        ]);
    }

    expect(tableRelationQueryCount(
        App\Filament\Resources\Events\Pages\ListEvents::class,
        'from "event_rsvps"',
    ))->toBe(1);
});

it('shows the attendee total of an event including guests', function (): void {
    $event = Event::query()->create([
        'title' => 'Enrollment Orientation',
        'type' => 'event',
        'start_datetime' => now(),
        'end_datetime' => now()->addDay(),
        'created_by' => Auth::id(),
        'max_attendees' => 10,
    ]);

    $attendee = User::factory()->create();

    EventRsvp::query()->create([
        'event_id' => $event->id,
        'user_id' => $attendee->id,
        'response' => 'attending',
        'guest_count' => 2,
    ]);

    $component = Livewire::test(App\Filament\Resources\Events\Pages\ListEvents::class);

    expect($component->html())->toContain('3/10');
});

it('counts the faculty and courses of every department with aggregates', function (): void {
    Department::factory()->count(6)->create();

    $queries = tableRelationQueries(function (): void {
        Livewire::test(App\Filament\Resources\Departments\Pages\ListDepartments::class)
            ->call('loadTable')
            ->assertSuccessful();
    });

    expect(tableRelationQueriesMatching($queries, 'from "faculty" where "faculty"."department_id"'))->toBe([])
        ->and(tableRelationQueriesMatching($queries, 'from "courses" where "courses"."department_id"'))->toBe([]);
});

it('loads the roles of every listed user in one query', function (): void {
    User::factory()->count(6)->create();

    $queries = tableRelationQueries(function (): void {
        Livewire::test(App\Filament\Resources\Users\Pages\ListUsers::class)
            ->assertSuccessful();
    });

    expect(tableRelationQueriesMatching($queries, 'model_has_roles"."model_id" in'))->toHaveCount(1);
});

it('loads the student and course of every enrollment in one query', function (): void {
    $course = Course::factory()->create(['code' => 'BSCS']);

    foreach (range(1, 6) as $ignored) {
        $student = Student::factory()->create(['course_id' => $course->id]);

        StudentEnrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2024 - 2025',
            'semester' => 2,
        ]);
    }

    $queries = tableRelationQueries(function (): void {
        Livewire::test(App\Filament\Resources\StudentEnrollments\Pages\ListStudentEnrollments::class)
            ->assertSuccessful();
    });

    expect(tableRelationQueriesMatching($queries, 'from "students" where "students"."id" in'))->toHaveCount(1)
        ->and(tableRelationQueriesMatching($queries, 'from "courses" where "courses"."id" in'))->toHaveCount(1);
});

it('clears the previous semester of the selected students with one clearance query', function (): void {
    $students = Student::factory()->count(6)->create();

    $queries = tableRelationQueries(function () use ($students): void {
        Livewire::test(App\Filament\Resources\Students\Pages\ListStudents::class)
            ->callTableBulkAction('bulkClearPreviousSemester', $students, ['confirm' => true, 'remarks' => 'Bulk cleared'])
            ->assertHasNoActionErrors();
    });

    expect(tableRelationQueriesMatching($queries, 'from "student_clearances" where "student_clearances"."student_id" = ?'))->toBe([])
        ->and(tableRelationQueriesMatching($queries, 'from "student_clearances" where "student_clearances"."student_id" in'))->not->toBeEmpty();

    expect(StudentClearance::query()
        ->where('academic_year', '2024 - 2025')
        ->where('semester', 1)
        ->where('is_cleared', true)
        ->count())->toBe(6);
});

it('marks the selected students as not cleared with one clearance query', function (): void {
    $students = Student::factory()->count(6)->create();

    foreach ($students as $student) {
        StudentClearance::query()->create([
            'student_id' => $student->id,
            'academic_year' => '2024 - 2025',
            'semester' => 1,
            'is_cleared' => true,
        ]);
    }

    $queries = tableRelationQueries(function () use ($students): void {
        Livewire::test(App\Filament\Resources\Students\Pages\ListStudents::class)
            ->callTableBulkAction('bulkNotClearPreviousSemester', $students, ['confirm' => true, 'remarks' => 'Bulk not cleared'])
            ->assertHasNoActionErrors();
    });

    expect(tableRelationQueriesMatching($queries, 'from "student_clearances" where "student_clearances"."student_id" = ?'))->toBe([])
        ->and(tableRelationQueriesMatching($queries, 'from "student_clearances" where "student_clearances"."student_id" in'))->not->toBeEmpty();

    expect(StudentClearance::query()
        ->where('academic_year', '2024 - 2025')
        ->where('semester', 1)
        ->where('is_cleared', false)
        ->count())->toBe(6);
});

it('shows the roles assigned to the account of a faculty member', function (): void {
    $faculty = Faculty::factory()->create(['email' => 'faculty.roles@example.test']);

    $portalUser = User::factory()->create(['email' => 'faculty.roles@example.test']);
    $portalUser->assignRole(Role::findOrCreate('instructor', 'web'));

    $component = Livewire::test(ListFaculties::class);

    expect($component->html())->toContain('Roles')
        ->and($component->html())->toContain('instructor');
});
