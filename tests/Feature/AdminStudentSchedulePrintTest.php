<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AdminStudentSchedulePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_view_student_schedule_with_correct_print_data()
    {
        // 1. Setup Data
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $course = Course::factory()->create();
        $student = Student::factory()->create([
            'course_id' => $course->id,
            'status' => 'enrolled',
        ]);

        // Set Current Period
        GeneralSetting::create([
            'school_starting_date' => '2023-08-01',
            'school_ending_date' => '2024-05-31',
            'semester' => 1,
        ]);

        $subject = Subject::factory()->create(['course_id' => $course->id, 'units' => 3]);
        $faculty = Faculty::factory()->create();
        $room = Room::factory()->create(['name' => 'Room 101']);

        $class = Classes::factory()->create([
            'subject_code' => $subject->code,
            'section' => 'A',
            'room_id' => $room->id,
            'faculty_id' => $faculty->id,
            'semester' => '1',
            'school_year' => '2023-2024',
            'subject_id' => $subject->id,
        ]);

        // Add Schedule: Mon 07:30 - 09:00
        Schedule::create([
            'class_id' => $class->id,
            'room_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '07:30:00',
            'end_time' => '09:00:00',
        ]);

        // Enroll Student (StudentEnrollment is the term record)
        $enrollment = StudentEnrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'semester' => 1,
            'academic_year' => 1,
            'school_year' => '2023-2024',
            'status' => 'enrolled',
        ]);

        // SubjectEnrollment (Actual class enrollment)
        // Note: The controller logic relies on linking via ClassEnrollment or SubjectEnrollment?
        // Controller: $student->classEnrollments() -> whereHas('class', ...)
        // We need to create a ClassEnrollment or link the SubjectEnrollment to the class.
        // Looking at Model `Student`, `classEnrollments()` is likely `hasMany(ClassEnrollment::class)`.

        // Let's check if ClassEnrollment exists
        if (class_exists(\App\Models\ClassEnrollment::class)) {
            \App\Models\ClassEnrollment::create([
                'class_id' => $class->id,
                'student_id' => $student->id,
            ]);
        }
        // Fallback if system uses SubjectEnrollment for everything, though 'classEnrollments' suggests a model.
        // Based on previous reads, ClassEnrollmentFactory exists.

        // 2. Act & Assert
        $this->actingAs($admin)
            ->get(route('administrators.students.show', $student->id))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('administrators/students/show', false)
                ->has('student.current_enrolled_classes')
                 // We assert that the data structure is prepared correctly for the frontend
                ->where('student.current_enrolled_classes.0.subject_code', $subject->code)
                ->where('student.current_enrolled_classes.0.schedules.0.day', 'Monday')
                ->where('student.current_enrolled_classes.0.schedules.0.start_time', '07:30')
            );
    }
}
