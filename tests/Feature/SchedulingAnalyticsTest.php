<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SchedulingAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable activity logging to avoid missing table error
        activity()->disableLogging();

        // Disable Inertia page existence check to avoid false positives in test environment
        config(['inertia.testing.ensure_pages_exist' => false]);

        // Setup General Settings for the current academic period
        // Assuming current year is 2025 based on date, let's set a fixed date in settings
        // GeneralSettingsService defaults to date('Y'). Let's assume we are testing for "2024 - 2025"

        GeneralSetting::factory()->create([
            'school_starting_date' => '2024-08-01',
            'school_ending_date' => '2025-05-30',
            'semester' => 1,
        ]);
    }

    public function test_scheduling_analytics_page_loads_with_correct_data()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Create Data
        $course = Course::factory()->create(['code' => 'BSIT', 'title' => 'Information Technology']);
        $subject = Subject::factory()->create(['code' => 'IT101', 'title' => 'Intro to IT', 'course_id' => $course->id]);
        $faculty = Faculty::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $room = Room::factory()->create(['name' => 'Lab 1']);

        // Valid Class (College 1st Year)
        $class1 = Classes::factory()->create([
            'subject_code' => 'IT101',
            'subject_id' => $subject->id,
            'faculty_id' => $faculty->id,
            'room_id' => $room->id,
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'academic_year' => 1, // 1st Year
            'classification' => 'college',
            'course_codes' => [$course->id], // Store ID as array
            'section' => 'A',
            'grade_level' => null,
        ]);

        // Schedule for Class 1
        Schedule::factory()->create([
            'class_id' => $class1->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'room_id' => $room->id,
        ]);

        // Valid Class (SHS Grade 11)
        $class2 = Classes::factory()->create([
            'subject_code' => 'MATH11',
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'classification' => 'shs',
            'grade_level' => 'Grade 11',
            'section' => 'B',
            'course_codes' => [],
        ]);

        // Schedule for Class 2
        Schedule::factory()->create([
            'class_id' => $class2->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        // Invalid Class (Wrong School Year)
        Classes::factory()->create([
            'school_year' => '2023 - 2024',
            'semester' => 1,
        ]);

        // Class without schedule (Course should NOT appear in filter)
        $unusedCourse = Course::factory()->create(['code' => 'BZN', 'title' => 'Business']);
        Classes::factory()->create([
            'subject_code' => 'BUS101',
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'academic_year' => 1, // Set to 1st Year to avoid random year levels
            'course_codes' => [$unusedCourse->id],
            // No schedule created
        ]);

        $response = $this->actingAs($admin)
            ->get(route('administrators.scheduling-analytics.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('administrators/scheduling-analytics')
            ->has('schedule_data', 3) // Should have 2 scheduled classes + 1 unscheduled class (data includes all classes)
            ->has('filters.available_courses', 1) // Should ONLY have BSIT (from scheduled class), NOT BZN
            ->where('filters.available_courses.0.code', 'BSIT')
            ->where('filters.available_year_levels', ['1st Year', 'Grade 11'])
            // Verify specific data structure for the first class (College)
            ->where('schedule_data.0.subject_code', 'IT101')
            ->where('schedule_data.0.grade_level', '1st Year')
            ->where('schedule_data.0.course_ids', [$course->id])
            // Verify second class (SHS)
            ->where('schedule_data.1.grade_level', 'Grade 11')
        );
    }

    public function test_scheduling_analytics_includes_available_rooms()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Create Rooms
        $room1 = Room::factory()->create(['name' => 'Lab 101', 'is_active' => true]);
        $room2 = Room::factory()->create(['name' => 'Lab 102', 'is_active' => true]);
        $unusedRoom = Room::factory()->create(['name' => 'Unused Room', 'is_active' => true]);

        // Create class with schedule in room1
        $class1 = Classes::factory()->create([
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'room_id' => $room1->id,
        ]);

        Schedule::factory()->create([
            'class_id' => $class1->id,
            'room_id' => $room1->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        // Create class with schedule in room2
        $class2 = Classes::factory()->create([
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'room_id' => $room2->id,
        ]);

        Schedule::factory()->create([
            'class_id' => $class2->id,
            'room_id' => $room2->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('administrators.scheduling-analytics.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->has('filters.available_rooms', 2) // Only rooms with schedules
            ->where('filters.available_rooms.0.name', 'Lab 101')
            ->where('filters.available_rooms.1.name', 'Lab 102')
        );
    }

    public function test_scheduling_analytics_includes_available_faculty()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Create Faculty
        $faculty1 = Faculty::factory()->create([
            'first_name' => 'Alice',
            'last_name' => 'Anderson',
            'department' => 'IT',
        ]);
        $faculty2 = Faculty::factory()->create([
            'first_name' => 'Bob',
            'last_name' => 'Brown',
            'department' => 'Business',
        ]);
        Faculty::factory()->create([
            'first_name' => 'Charlie',
            'last_name' => 'Clark',
            'department' => 'HR',
        ]); // Not assigned to any class

        // Create class with faculty1
        $class1 = Classes::factory()->create([
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'faculty_id' => $faculty1->id,
        ]);

        Schedule::factory()->create([
            'class_id' => $class1->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        // Create class with faculty2
        $class2 = Classes::factory()->create([
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'faculty_id' => $faculty2->id,
        ]);

        Schedule::factory()->create([
            'class_id' => $class2->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('administrators.scheduling-analytics.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->has('filters.available_faculty', 2) // Only faculty with classes
            ->where('filters.available_faculty.0.name', 'Alice Anderson')
            ->where('filters.available_faculty.0.department', 'IT')
            ->where('filters.available_faculty.1.name', 'Bob Brown')
            ->where('filters.available_faculty.1.department', 'Business')
        );
    }

    public function test_student_search_returns_matching_students()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Create students - use document_location_id => null to avoid table issue
        $student1 = Student::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'middle_name' => null,
            'student_id' => 123456,
            'document_location_id' => null,
        ]);

        Student::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Smith',
            'middle_name' => null,
            'student_id' => 789012,
            'document_location_id' => null,
        ]);

        Student::factory()->create([
            'first_name' => 'Janet',
            'last_name' => 'Williams',
            'middle_name' => null,
            'student_id' => 345678,
            'document_location_id' => null,
        ]);

        // Search for "Jane" should return Jane Doe and Janet Williams
        $response = $this->actingAs($admin)
            ->getJson(route('administrators.scheduling-analytics.students.search', ['query' => 'Jane']));

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'students');
        $response->assertJsonPath('students.0.name', 'Doe, Jane ');

        // Search by student ID
        $response = $this->actingAs($admin)
            ->getJson(route('administrators.scheduling-analytics.students.search', ['query' => '123456']));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'students');
        $response->assertJsonPath('students.0.student_id', 123456);
    }

    public function test_student_search_requires_minimum_query_length()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Single character search should fail validation
        $response = $this->actingAs($admin)
            ->getJson(route('administrators.scheduling-analytics.students.search', ['query' => 'J']));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['query']);
    }

    public function test_get_student_schedule_returns_enrolled_classes()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        // Create course and student - use document_location_id => null to avoid table issue
        $course = Course::factory()->create(['code' => 'BSCS', 'title' => 'Computer Science']);
        $student = Student::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Student',
            'middle_name' => null,
            'student_id' => 202500,
            'course_id' => $course->id,
            'academic_year' => 2,
            'document_location_id' => null,
        ]);

        // Create faculty
        $faculty = Faculty::factory()->create([
            'first_name' => 'Prof',
            'last_name' => 'Teacher',
        ]);

        // Create room
        $room = Room::factory()->create(['name' => 'Room 101']);

        // Create subjects
        $subject1 = Subject::factory()->create([
            'code' => 'CS201',
            'title' => 'Data Structures',
            'course_id' => $course->id,
        ]);

        $subject2 = Subject::factory()->create([
            'code' => 'CS202',
            'title' => 'Algorithms',
            'course_id' => $course->id,
        ]);

        // Create classes for current academic period
        $class1 = Classes::factory()->create([
            'subject_code' => 'CS201',
            'subject_id' => $subject1->id,
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'faculty_id' => $faculty->id,
            'section' => 'A',
        ]);

        Schedule::factory()->create([
            'class_id' => $class1->id,
            'room_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
        ]);

        $class2 = Classes::factory()->create([
            'subject_code' => 'CS202',
            'subject_id' => $subject2->id,
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'faculty_id' => $faculty->id,
            'section' => 'A',
        ]);

        Schedule::factory()->create([
            'class_id' => $class2->id,
            'room_id' => $room->id,
            'day_of_week' => 'Wednesday',
            'start_time' => '13:00:00',
            'end_time' => '15:00:00',
        ]);

        // Enroll student in both classes
        ClassEnrollment::create([
            'class_id' => $class1->id,
            'student_id' => $student->id,
        ]);

        ClassEnrollment::create([
            'class_id' => $class2->id,
            'student_id' => $student->id,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('administrators.scheduling-analytics.students.schedule', ['studentId' => $student->id]));

        $response->assertStatus(200);

        $response->assertJsonPath('student.student_id', 202500);
        $response->assertJsonPath('student.name', 'Student, Test ');
        $response->assertJsonPath('student.course', 'BSCS');
        $response->assertJsonPath('student.academic_year', 2);

        $response->assertJsonCount(2, 'schedule');
        $response->assertJsonPath('schedule.0.subject_code', 'CS201');
        $response->assertJsonPath('schedule.0.faculty_name', 'Prof Teacher');
        $response->assertJsonPath('schedule.1.subject_code', 'CS202');
    }

    public function test_get_student_schedule_returns_404_for_nonexistent_student()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('administrators.scheduling-analytics.students.schedule', ['studentId' => 99999]));

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'Student not found');
    }

    public function test_schedule_data_includes_room_id_in_schedules()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        $room = Room::factory()->create(['name' => 'Room A', 'is_active' => true]);

        $class = Classes::factory()->create([
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'subject_code' => 'TEST101',
        ]);

        Schedule::factory()->create([
            'class_id' => $class->id,
            'room_id' => $room->id,
            'day_of_week' => 'Friday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('administrators.scheduling-analytics.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->where('schedule_data.0.schedules.0.room_id', $room->id)
            ->where('schedule_data.0.schedules.0.room', 'Room A')
        );
    }

    public function test_schedule_data_includes_faculty_id()
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin->value,
        ]);

        $faculty = Faculty::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Faculty',
        ]);

        $class = Classes::factory()->create([
            'school_year' => '2024 - 2025',
            'semester' => 1,
            'subject_code' => 'FAC101',
            'faculty_id' => $faculty->id,
        ]);

        Schedule::factory()->create([
            'class_id' => $class->id,
            'day_of_week' => 'Thursday',
            'start_time' => '10:00:00',
            'end_time' => '11:00:00',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('administrators.scheduling-analytics.index'));

        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->where('schedule_data.0.faculty_id', $faculty->id)
            ->where('schedule_data.0.faculty_name', 'Test Faculty')
        );
    }
}
