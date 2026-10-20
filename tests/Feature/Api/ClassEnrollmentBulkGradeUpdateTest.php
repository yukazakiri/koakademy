<?php

declare(strict_types=1);

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * The bulk grade endpoint used to run three queries per student (a lookup, an
 * update and a relation load) with no transaction and no chunking, so a class
 * of 200 students turned into 600 statements in a single request.
 */
beforeEach(function (): void {
    $this->class = Classes::factory()->create();
    $this->user = User::factory()->create();
});

/**
 * @return array<string, mixed>
 */
function enrollmentState(array $overrides = []): array
{
    return array_merge([
        'prelim_grade' => null,
        'midterm_grade' => null,
        'finals_grade' => null,
        'total_average' => null,
    ], $overrides);
}

it('updates the grades of every student in one request', function (): void {
    $students = Student::factory()->count(3)->create();

    $enrollments = $students->map(fn (Student $student): ClassEnrollment => ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $student->id,
        ...enrollmentState(),
    ]));

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => $enrollments->map(fn (ClassEnrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'prelim_grade' => 80,
                'midterm_grade' => 90,
                'finals_grade' => 100,
            ])->all(),
        ]);

    $response->assertOk()
        ->assertJsonPath('data.total_updated', 3)
        ->assertJsonPath('data.total_errors', 0);

    foreach ($enrollments as $enrollment) {
        $fresh = $enrollment->fresh();

        expect($fresh->prelim_grade)->toBe(80.0)
            ->and($fresh->midterm_grade)->toBe(90.0)
            ->and($fresh->finals_grade)->toBe(100.0)
            // 80 * 0.3 + 90 * 0.3 + 100 * 0.4
            ->and($fresh->total_average)->toBe(91.0);
    }
});

it('computes the total average with the configured weights', function (): void {
    config()->set('class_grading.weights', [
        'prelim' => 0.5,
        'midterm' => 0.25,
        'finals' => 0.25,
    ]);

    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => Student::factory()->create()->id,
        ...enrollmentState(),
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => [[
                'enrollment_id' => $enrollment->id,
                'prelim_grade' => 100,
                'midterm_grade' => 0,
                'finals_grade' => 0,
            ]],
        ])
        ->assertOk();

    expect($enrollment->fresh()->total_average)->toBe(50.0);
});

it('keeps the stored total average when a grade is cleared', function (): void {
    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => Student::factory()->create()->id,
        ...enrollmentState([
            'prelim_grade' => 80,
            'midterm_grade' => 90,
            'finals_grade' => 100,
            'total_average' => 91,
        ]),
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => [[
                'enrollment_id' => $enrollment->id,
                'prelim_grade' => null,
            ]],
        ])
        ->assertOk();

    $fresh = $enrollment->fresh();

    expect($fresh->prelim_grade)->toBeNull()
        ->and($fresh->total_average)->toBe(91.0);
});

it('reports enrollments that belong to another class as errors', function (): void {
    $otherClass = Classes::factory()->create();

    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $otherClass->id,
        'student_id' => Student::factory()->create()->id,
        ...enrollmentState(),
    ]);

    $response = $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => [[
                'enrollment_id' => $enrollment->id,
                'prelim_grade' => 80,
                'midterm_grade' => 90,
                'finals_grade' => 100,
            ]],
        ]);

    $response->assertOk()
        ->assertJsonPath('data.total_updated', 0)
        ->assertJsonPath('data.total_errors', 1)
        ->assertJsonPath('data.errors.0.enrollment_id', $enrollment->id);

    expect($enrollment->fresh()->prelim_grade)->toBeNull();
});

it('keeps the audit trail of a bulk grade update', function (): void {
    Activity::query()->delete();

    $enrollment = ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => Student::factory()->create()->id,
        ...enrollmentState(),
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", [
            'grades' => [[
                'enrollment_id' => $enrollment->id,
                'prelim_grade' => 80,
                'midterm_grade' => 90,
                'finals_grade' => 100,
            ]],
        ])
        ->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'class_enrollments')
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(ClassEnrollment::class)
        ->and($activity->subject_id)->toBe($enrollment->id)
        ->and($activity->attribute_changes->get('attributes'))->toHaveKey('prelim_grade')
        ->and($activity->attribute_changes->get('old'))->toHaveKey('prelim_grade');
});

it('writes every grade with a single statement', function (): void {
    $students = Student::factory()->count(25)->create();

    $enrollments = $students->map(fn (Student $student): ClassEnrollment => ClassEnrollment::factory()->create([
        'class_id' => $this->class->id,
        'student_id' => $student->id,
        ...enrollmentState(),
    ]));

    $payload = [
        'grades' => $enrollments->map(fn (ClassEnrollment $enrollment): array => [
            'enrollment_id' => $enrollment->id,
            'prelim_grade' => 80,
            'midterm_grade' => 90,
            'finals_grade' => 100,
        ])->all(),
    ];

    $statements = [];

    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $this->actingAs($this->user, 'sanctum')
        ->patchJson("/api/class-enrollments/class/{$this->class->id}/bulk-grades", $payload)
        ->assertOk()
        ->assertJsonPath('data.total_updated', 25);

    $upserts = array_filter(
        $statements,
        static fn (string $sql): bool => str_contains($sql, 'on conflict')
    );

    $perStudentUpdates = array_filter(
        $statements,
        static fn (string $sql): bool => str_starts_with(mb_strtolower(mb_trim($sql)), 'update "class_enrollments"')
    );

    expect($upserts)->toHaveCount(1)
        ->and($perStudentUpdates)->toHaveCount(0);
});
