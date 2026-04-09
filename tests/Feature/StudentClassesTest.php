<?php

declare(strict_types=1);

use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('student can view classes (my academics) page', function () {
    // Create student user
    $user = User::factory()->create(['role' => 'student']);

    // Create student record
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'user_id' => $user->id,
        'email' => $user->email,
        'course_id' => $course->id,
    ]);

    // Create some subjects and curriculum
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'units' => 3,
        'academic_year' => 1,
        'semester' => 1,
    ]);

    // Create a class
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

    // Create General Settings
    App\Models\GeneralSetting::create([
        'school_starting_date' => '2023-08-01',
        'school_ending_date' => '2024-05-31',
        'semester' => 1,
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

    // Enroll student in subject
    SubjectEnrollment::create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'class_id' => $class->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2023-2024',
        'semester' => 1,
        'academic_year' => 1,
        'grade' => null,
        'is_credited' => false,
    ]);

    // Enroll student in class
    App\Models\ClassEnrollment::create([
        'class_id' => $class->id,
        'student_id' => $student->id,
    ]);

    config(['inertia.testing.ensure_pages_exist' => false]);

    $this->actingAs($user)
        ->get(route('student.classes.index'))
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('student/classes/index')
            ->has('faculty_data.classes', 1)
            ->where('faculty_data.classes.0.id', $class->id)
            ->has('curriculum')
            ->has('rooms')
        );
});

test('non-student cannot view classes page', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($user)
        ->get(route('student.classes.index'))
        ->assertForbidden();
});
