<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentClassShareService;

beforeEach(function (): void {
    $this->service = app(StudentClassShareService::class);

    App\Models\School::factory()->create();
});

it('returns empty array when user is null', function (): void {
    expect($this->service->getStudentClasses(null))->toBe([]);
});

it('returns empty array when user is not a student', function (): void {
    $user = User::factory()->create(['role' => UserRole::Admin->value]);

    expect($this->service->getStudentClasses($user))->toBe([]);
});

it('returns empty array when student record is not found', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student->value]);

    expect($this->service->getStudentClasses($user))->toBe([]);
});

it('returns empty array when student has no current enrollments', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student->value]);
    Student::factory()->create(['email' => $user->email]);

    expect($this->service->getStudentClasses($user))->toBe([]);
});

it('returns mapped classes when student has current enrollments', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student->value]);
    $student = Student::factory()->create(['email' => $user->email, 'user_id' => $user->id]);

    $currentYear = (int) date('Y');
    $schoolYear = $currentYear.' - '.($currentYear + 1);
    $semester = 1;

    $class = Classes::factory()->create([
        'school_year' => $schoolYear,
        'semester' => $semester,
        'section' => 'A',
        'settings' => array_merge(Classes::getDefaultSettings(), ['accent_color' => '#ef4444']),
    ]);

    ClassEnrollment::factory()->create([
        'student_id' => $student->id,
        'class_id' => $class->id,
        'status' => true,
    ]);

    $result = $this->service->getStudentClasses($user);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveKeys([
            'id', 'subject_code', 'subject_title', 'section', 'classification', 'students_count', 'accent_color',
        ])
        ->and($result[0]['id'])->toBe($class->id)
        ->and($result[0]['section'])->toBe('A')
        ->and($result[0]['accent_color'])->toBe('#ef4444');
});
