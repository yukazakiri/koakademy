<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentClearance;

beforeEach(function (): void {
    // Create general settings for testing
    GeneralSetting::query()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);
});

test('student can calculate previous academic period for 2nd semester', function (): void {
    $student = Student::factory()->create();

    $previous = $student->getPreviousAcademicPeriod('2024 - 2025', 2);

    expect($previous)->toEqual([
        'academic_year' => '2024 - 2025',
        'semester' => 1,
    ]);
});

test('student can calculate previous academic period for 1st semester', function (): void {
    $student = Student::factory()->create();

    $previous = $student->getPreviousAcademicPeriod('2024 - 2025', 1);

    expect($previous)->toEqual([
        'academic_year' => '2023 - 2024',
        'semester' => 2,
    ]);
});

test('legacy cleared records remain readable without controlling enrollment', function (): void {
    $student = Student::factory()->create();

    // Create previous semester clearance (1st semester of 2024-2025)
    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => true,
        'cleared_by' => 'Admin',
        'cleared_at' => now(),
    ]);

    $validation = $student->validateEnrollmentClearance('2024 - 2025', 2);

    expect($validation['allowed'])->toBeTrue()
        ->and($validation['clearance'])->toBeNull()
        ->and($student->getPreviousSemesterClearance('2024 - 2025', 2)->is_cleared)->toBeTrue();
});

test('legacy uncleared records no longer block enrollment', function (): void {
    $student = Student::factory()->create();

    // Create previous semester clearance but not cleared
    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => false,
    ]);

    $validation = $student->validateEnrollmentClearance('2024 - 2025', 2);

    expect($validation['allowed'])->toBeTrue()
        ->and($validation['message'])->toContain('retired')
        ->and($validation['clearance'])->toBeNull();
});

test('student passes enrollment validation when no previous clearance record exists', function (): void {
    $student = Student::factory()->create();

    // No clearance record created
    $validation = $student->validateEnrollmentClearance('2024 - 2025', 2);

    expect($validation['allowed'])->toBeTrue()
        ->and($validation['message'])->toContain('retired')
        ->and($validation['clearance'])->toBeNull();
});

test('student passes enrollment validation when clearance checking is disabled', function (): void {
    $student = Student::factory()->create();

    // Disable clearance checking
    GeneralSetting::query()->first()->update(['enable_clearance_check' => false]);

    // Create uncleared previous semester
    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => false,
    ]);

    $validation = $student->validateEnrollmentClearance('2024 - 2025', 2);

    expect($validation['allowed'])->toBeTrue()
        ->and($validation['message'])->toContain('retired');
});

test('student can get previous semester clearance record', function (): void {
    $student = Student::factory()->create();

    $clearance = StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => true,
    ]);

    $retrieved = $student->getPreviousSemesterClearance('2024 - 2025', 2);

    expect($retrieved)->toBeInstanceOf(StudentClearance::class)
        ->and($retrieved->id)->toBe($clearance->id)
        ->and($retrieved->is_cleared)->toBeTrue();
});

test('student returns null when previous semester clearance does not exist', function (): void {
    $student = Student::factory()->create();

    $retrieved = $student->getPreviousSemesterClearance('2024 - 2025', 2);

    expect($retrieved)->toBeNull();
});

test('hasPreviousSemesterClearance returns true when cleared', function (): void {
    $student = Student::factory()->create();

    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => true,
    ]);

    expect($student->hasPreviousSemesterClearance('2024 - 2025', 2))->toBeTrue();
});

test('hasPreviousSemesterClearance returns false when not cleared', function (): void {
    $student = Student::factory()->create();

    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => false,
    ]);

    expect($student->hasPreviousSemesterClearance('2024 - 2025', 2))->toBeFalse();
});

test('hasPreviousSemesterClearance returns false when no record exists', function (): void {
    $student = Student::factory()->create();

    expect($student->hasPreviousSemesterClearance('2024 - 2025', 2))->toBeFalse();
});

test('legacy records remain readable across academic years', function (): void {
    $student = Student::factory()->create();

    // Create previous year's 2nd semester clearance
    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2023 - 2024',
        'semester' => 2,
        'is_cleared' => true,
    ]);

    // Validating for 1st semester of 2024-2025 should check 2023-2024 Sem 2
    $validation = $student->validateEnrollmentClearance('2024 - 2025', 1);

    expect($validation['allowed'])->toBeTrue()
        ->and($student->getPreviousSemesterClearance('2024 - 2025', 1)->academic_year)->toBe('2023 - 2024')
        ->and($student->getPreviousSemesterClearance('2024 - 2025', 1)->semester)->toBe(2);
});

test('validation message explains that legacy clearance no longer blocks enrollment', function (): void {
    $student = Student::factory()->create();

    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => false,
    ]);

    $validation = $student->validateEnrollmentClearance('2024 - 2025', 2);

    expect($validation['message'])->toContain('retired')->toContain('does not affect enrollment');
});

test('validation message is descriptive for allowed enrollment', function (): void {
    $student = Student::factory()->create();

    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 1,
        'is_cleared' => true,
    ]);

    $validation = $student->validateEnrollmentClearance('2024 - 2025', 2);

    expect($validation['message'])->toContain('retired')->toContain('does not affect enrollment');
});
