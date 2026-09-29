<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentStatusRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    School::factory()->create();
});

/**
 * The students table foreign key that points at each related record.
 *
 * @var array<string, string>
 */
const RELATED_TABLES = [
    'student_contacts' => 'student_contact_id',
    'student_parents_info' => 'student_parent_info',
    'student_education_info' => 'student_education_id',
    'students_personal_info' => 'student_personal_id',
];

/**
 * Minimal but valid payload for the full student update form.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function studentUpdatePayload(array $overrides = []): array
{
    return array_merge([
        'student_type' => 'college',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'gender' => 'male',
        'birth_date' => '2005-01-15',
        'academic_year' => 1,
        'course_id' => Course::factory()->create()->id,
        'status' => 'enrolled',
    ], $overrides);
}

it('redirects to the student show page after a full update instead of the index', function (): void {
    withoutVite();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1]);

    actingAs($user)
        ->put(route('administrators.students.update', $student), studentUpdatePayload(['academic_year' => 2]))
        ->assertRedirect(route('administrators.students.show', $student))
        ->assertSessionHasNoErrors();

    expect($student->refresh()->academic_year)->toBe(2);
});

it('does not rewrite the related student records when only the year level changes', function (): void {
    withoutVite();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1]);

    // Create the related rows via a first full update, then snapshot them.
    $populateRelated = [
        'personal_contact' => '09171234567',
        'fathers_name' => 'Ramon',
        'mothers_name' => 'Luz',
        'birthplace' => 'Manila',
        'elementary_school' => 'Rizal Elementary',
    ];

    actingAs($user)
        ->put(route('administrators.students.update', $student), studentUpdatePayload($populateRelated))
        ->assertSessionHasNoErrors();

    $student->refresh();

    $before = [];

    foreach (RELATED_TABLES as $table => $foreignKey) {
        $before[$table] = DB::table($table)->where('id', $student->{$foreignKey})->first();
    }

    expect($before)->each->toBeObject();

    // Capture only the writes the second request performs.
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^(update|insert)\s/i', mb_trim($query->sql)) === 1) {
            $writes[] = $query->sql;
        }
    });

    // Second update changes nothing but the year level.
    actingAs($user)
        ->put(route('administrators.students.update', $student), studentUpdatePayload([
            ...$populateRelated,
            'academic_year' => 3,
        ]))
        ->assertSessionHasNoErrors();

    expect($student->refresh()->academic_year)->toBe(3);

    // No related-table writes should have happened.
    foreach (RELATED_TABLES as $table => $foreignKey) {
        $touched = array_filter(
            $writes,
            static fn (string $sql): bool => str_contains($sql, $table),
        );

        expect($touched)->toBe([], "Expected no writes to {$table} when only the year level changed.");

        // And the persisted row is byte-for-byte identical.
        $after = DB::table($table)->where('id', $student->{$foreignKey})->first();
        $normalisedBefore = (array) $before[$table];
        $normalisedAfter = (array) $after;

        unset($normalisedBefore['updated_at'], $normalisedAfter['updated_at']);

        expect($normalisedAfter)->toBe($normalisedBefore);
    }
});

it('does not issue a schema catalog query per related attribute when saving a student', function (): void {
    withoutVite();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1]);

    $catalogQueries = 0;
    DB::listen(function ($query) use (&$catalogQueries): void {
        $sql = mb_strtolower($query->sql);
        if (str_contains($sql, 'information_schema.columns') || str_contains($sql, 'pg_attribute') || str_contains($sql, 'pg_catalog')) {
            $catalogQueries++;
        }
    });

    actingAs($user)
        ->put(route('administrators.students.update', $student), studentUpdatePayload(['academic_year' => 4]))
        ->assertSessionHasNoErrors();

    // One column listing per related table (4), instead of one per attribute (~120).
    expect($catalogQueries)->toBeLessThanOrEqual(6);
});

it('updates the year level through the quick-update endpoint and returns the derived payload', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1, 'status' => 'enrolled']);

    $response = actingAs($user)
        ->patch(route('administrators.students.quick-update', $student), [
            'academic_year' => 3,
        ])
        ->assertOk()
        ->assertJsonPath('student.academic_year', 3)
        ->assertJsonPath('student.formatted_academic_year', '3rd year')
        ->assertJsonPath('student.status', 'enrolled');

    expect($student->refresh()->academic_year)->toBe(3);
    expect($response->json('message'))->toBe('Student updated successfully.');
});

it('updates the status through the quick-update endpoint and syncs the status record', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 2, 'status' => 'enrolled']);

    actingAs($user)
        ->patch(route('administrators.students.quick-update', $student), [
            'status' => StudentStatus::OnLeave->value,
        ])
        ->assertOk()
        ->assertJsonPath('student.status', StudentStatus::OnLeave->value);

    expect($student->refresh()->status)->toBe(StudentStatus::OnLeave);

    $statusRecords = StudentStatusRecord::query()->where('student_id', $student->id)->get();

    expect($statusRecords)->not->toBeEmpty();

    foreach ($statusRecords as $record) {
        expect($record->status)->toBe(StudentStatus::OnLeave);
    }
});

it('updates both the year level and status in a single quick-update call', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1, 'status' => 'applicant']);

    actingAs($user)
        ->patch(route('administrators.students.quick-update', $student), [
            'academic_year' => 4,
            'status' => StudentStatus::Enrolled->value,
        ])
        ->assertOk()
        ->assertJsonPath('student.academic_year', 4)
        ->assertJsonPath('student.status', StudentStatus::Enrolled->value);

    expect($student->refresh())
        ->academic_year->toBe(4)
        ->status->toBe(StudentStatus::Enrolled);
});

it('rejects an out of range year level on the quick-update endpoint', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1]);

    actingAs($user)
        ->patch(route('administrators.students.quick-update', $student), [
            'academic_year' => 9,
        ])
        ->assertSessionHasErrors('academic_year');

    expect($student->refresh()->academic_year)->toBe(1);
});

it('rejects an unknown status on the quick-update endpoint', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1, 'status' => 'enrolled']);

    actingAs($user)
        ->patch(route('administrators.students.quick-update', $student), [
            'status' => 'not_a_real_status',
        ])
        ->assertSessionHasErrors('status');

    expect($student->refresh()->status)->toBe(StudentStatus::Enrolled);
});

it('rejects a request that contains no supported fields', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 1]);

    actingAs($user)
        ->patch(route('administrators.students.quick-update', $student), [])
        ->assertStatus(422)
        ->assertJsonPath('message', 'No supported fields were provided.');
});

it('exposes the raw year level on the show page for the inline editor', function (): void {
    withoutVite();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create(['academic_year' => 2]);

    actingAs($user)
        ->get(route('administrators.students.show', $student))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/students/show', false)
            ->where('student.academic_year', '2nd year')
            ->where('student.academic_year_value', 2)
        );
});
