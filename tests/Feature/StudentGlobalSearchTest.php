<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Cache::forget('general_settings_id');

    School::factory()->create(['id' => 1]);

    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
    ]);
});

it('only surfaces classes the student is enrolled in', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);

    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    $enrolledSubject = Subject::factory()->create(['code' => 'IT701', 'title' => 'Matching Subject']);
    $otherSubject = Subject::factory()->create(['code' => 'IT702', 'title' => 'Matching Subject']);

    $enrolledClass = Classes::factory()->create([
        'subject_id' => $enrolledSubject->id,
        'subject_code' => $enrolledSubject->code,
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);

    $foreignClass = Classes::factory()->create([
        'subject_id' => $otherSubject->id,
        'subject_code' => $otherSubject->code,
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);

    ClassEnrollment::factory()->create(['class_id' => $enrolledClass->id, 'student_id' => $student->id]);

    actingAs($user);

    $classes = getJson('/student/search?q=Matching&type=classes')->assertOk()->json('classes');

    expect(array_column($classes, 'id'))->toBe([$enrolledClass->id])
        ->and(array_column($classes, 'id'))->not->toContain($foreignClass->id);
});

it('keeps enrollment results limited to the signed in student', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);

    $student = Student::factory()->create([
        'user_id' => $user->id,
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    $otherStudent = Student::factory()->create(['school_id' => 1, 'institution_id' => 1]);

    $course = Course::factory()->create(['code' => 'BSIT', 'title' => 'BSIT']);

    $own = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    $foreign = StudentEnrollment::factory()->create([
        'student_id' => $otherStudent->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    actingAs($user);

    $enrollments = getJson('/student/search?q=enrolled&type=enrollments')->assertOk()->json('enrollments');

    expect(array_column($enrollments, 'id'))->toBe([$own->id])
        ->and(array_column($enrollments, 'id'))->not->toContain($foreign->id);
});
