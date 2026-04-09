<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\OnboardingFeature;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('student can view schedule page', function () {
    // Create student user
    $user = User::factory()->create(['role' => UserRole::Student]);

    // Create student record
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'course_id' => $course->id,
    ]);

    // Create a class
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $room = Room::factory()->create();
    $faculty = Faculty::factory()->create();

    $class = Classes::create([
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $room->id,
        'faculty_id' => $faculty->id,
        'maximum_slots' => 40,
        'semester' => '1',
        'school_year' => '2023-2024',
        'subject_id' => $subject->id,
    ]);

    // Create Schedule
    App\Models\Schedule::create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    // Create General Settings
    App\Models\GeneralSetting::create([
        'school_starting_date' => '2023-08-01',
        'school_ending_date' => '2024-05-31',
        'semester' => 1,
    ]);

    OnboardingFeature::factory()->create([
        'feature_key' => 'onboarding-student-schedule',
        'audience' => 'student',
        'is_active' => true,
    ]);

    // Enroll student in term
    $enrollment = App\Models\StudentEnrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2023-2024',
        'status' => 'Enrolled',
    ]);

    // Enroll student in class
    App\Models\ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->actingAs($user)
        ->get(route('student.schedule'))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/schedule')
            ->has('faculty_data.classes', 1)
            ->where('faculty_data.classes.0.id', $class->id)
            ->has('rooms')
        );
});

test('non-student cannot view schedule page', function () {
    $user = User::factory()->create(['role' => UserRole::Instructor]);

    $this->actingAs($user)
        ->get(route('student.schedule'))
        ->assertForbidden();
});
