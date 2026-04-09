<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function (): void {
    config()->set('scout.driver', 'collection');

    Cache::forget('general_settings_id');

    School::factory()->create(['id' => 1]);

    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
    ]);
});

it('returns current students, classes, and enrollments from the administrator global search', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $course = Course::factory()->create([
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
    ]);

    $student = Student::factory()->create([
        'student_id' => 205001,
        'first_name' => 'Alicia',
        'last_name' => 'Mercado',
        'middle_name' => 'Santos',
        'course_id' => $course->id,
        'school_id' => 1,
        'institution_id' => 1,
    ]);

    $subject = Subject::factory()->create([
        'code' => 'IT401',
        'title' => 'Capstone Project',
        'course_id' => $course->id,
    ]);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'classification' => 'college',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'course_codes' => [(string) $course->id],
        'room_id' => Room::factory()->create()->id,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 4,
        'status' => 'enrolled',
    ]);

    actingAs($admin);

    $studentSearchQuery = rawurlencode($student->full_name);

    getJson(portalUrlForAdministrators("/administrators/search?q={$studentSearchQuery}&type=students"))
        ->assertOk()
        ->assertJsonPath('students.0.id', $student->id)
        ->assertJsonPath('students.0.name', $student->full_name);

    getJson(portalUrlForAdministrators('/administrators/search?q=Capstone&type=classes'))
        ->assertOk()
        ->assertJsonPath('classes.0.id', $class->id)
        ->assertJsonPath('classes.0.subject_code', $subject->code);

    getJson(portalUrlForAdministrators("/administrators/search?q={$studentSearchQuery}&type=enrollments"))
        ->assertOk()
        ->assertJsonPath('enrollments.0.id', $enrollment->id)
        ->assertJsonPath('enrollments.0.student_name', $student->full_name);
});
