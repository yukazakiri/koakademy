<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
});

it('passes scheduling_policy props to both enrollment create and edit workbenches', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'semester' => 1,
    ]);

    app(GeneralSettingsService::class)->updateSchedulingConfig([
        'check_student_cohort_conflicts' => true,
        'check_synchronous_online_conflicts' => false,
        'check_asynchronous_online_conflicts' => true,
        'allow_sunday_scheduling' => true,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['id' => 930101, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT101', 'lecture' => 3, 'laboratory' => 0]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Enrolled',
    ]);

    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 300,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/enrollments/create'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scheduling_policy.check_student_cohort_conflicts', true)
            ->where('scheduling_policy.check_synchronous_online_conflicts', false)
            ->where('scheduling_policy.check_asynchronous_online_conflicts', true)
            ->where('scheduling_policy.allow_sunday_scheduling', true));

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/edit"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scheduling_policy.check_student_cohort_conflicts', true)
            ->where('scheduling_policy.check_synchronous_online_conflicts', false)
            ->where('scheduling_policy.check_asynchronous_online_conflicts', true)
            ->where('scheduling_policy.allow_sunday_scheduling', true));
});

it('serializes delivery modes and policy-aware conflict participation on getSubjectSections', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'semester' => 1,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSIT']);
    $room = Room::factory()->create(['name' => 'Room 204']);
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT202',
        'lecture' => 3,
        'laboratory' => 0,
    ]);

    $f2fClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'IT202',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'A-F2F',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'room_id' => $room->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);
    Schedule::query()->create([
        'class_id' => $f2fClass->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'room_id' => $room->id,
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
    ]);

    $asyncClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'IT202',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'B-ASYNC',
        'delivery_mode' => Classes::DELIVERY_ONLINE,
        'room_id' => null,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);
    Schedule::query()->create([
        'class_id' => $asyncClass->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'room_id' => null,
        'delivery_mode' => Classes::DELIVERY_ONLINE,
    ]);

    $syncClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'IT202',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'C-SYNC',
        'delivery_mode' => Classes::DELIVERY_ONLINE_SYNCHRONOUS,
        'room_id' => null,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);
    Schedule::query()->create([
        'class_id' => $syncClass->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
        'room_id' => null,
        'delivery_mode' => Classes::DELIVERY_ONLINE_SYNCHRONOUS,
    ]);

    $mixedClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'IT202',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'D-MIXED',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'room_id' => $room->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);
    Schedule::query()->create([
        'class_id' => $mixedClass->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'room_id' => $room->id,
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
    ]);
    Schedule::query()->create([
        'class_id' => $mixedClass->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '08:00',
        'end_time' => '09:00',
        'room_id' => null,
        'delivery_mode' => Classes::DELIVERY_ONLINE,
    ]);

    $url = portalUrlForAdministrators("/administrators/enrollments/api/sections?subject_id={$subject->id}&course_id={$course->id}&school_year=2025%20-%202026&semester=1");

    // 1. Default policy: F2F=checked, Sync=checked, Async=exempt
    $defaultSections = collect($this->actingAs($admin)->getJson($url)->assertOk()->json())->keyBy('section');

    expect($defaultSections->get('A-F2F')['delivery_mode'])->toBe('face_to_face')
        ->and($defaultSections->get('A-F2F')['schedules'][0]['delivery_mode'])->toBe('face_to_face')
        ->and($defaultSections->get('A-F2F')['schedules'][0]['room'])->toBe('Room 204')
        ->and($defaultSections->get('A-F2F')['schedules'][0]['checks_time_conflicts'])->toBeTrue();

    expect($defaultSections->get('B-ASYNC')['delivery_mode'])->toBe('online')
        ->and($defaultSections->get('B-ASYNC')['delivery_summary'])->toBe('online')
        ->and($defaultSections->get('B-ASYNC')['schedules'][0]['delivery_mode'])->toBe('online')
        ->and($defaultSections->get('B-ASYNC')['schedules'][0]['room'])->toBe('Online (Async)')
        ->and($defaultSections->get('B-ASYNC')['schedules'][0]['checks_time_conflicts'])->toBeFalse()
        ->and($defaultSections->get('B-ASYNC')['schedule'])->toContain('Online (Async)');

    expect($defaultSections->get('C-SYNC')['delivery_mode'])->toBe('online_synchronous')
        ->and($defaultSections->get('C-SYNC')['schedules'][0]['delivery_mode'])->toBe('online_synchronous')
        ->and($defaultSections->get('C-SYNC')['schedules'][0]['room'])->toBe('Online (Sync)')
        ->and($defaultSections->get('C-SYNC')['schedules'][0]['checks_time_conflicts'])->toBeTrue();

    expect($defaultSections->get('D-MIXED')['delivery_summary'])->toBe('mixed')
        ->and($defaultSections->get('D-MIXED')['schedules'][0]['checks_time_conflicts'])->toBeTrue()
        ->and($defaultSections->get('D-MIXED')['schedules'][1]['delivery_mode'])->toBe('online')
        ->and($defaultSections->get('D-MIXED')['schedules'][1]['checks_time_conflicts'])->toBeFalse();

    // 2. Custom policy: Async=checked, Sync=exempt
    app(GeneralSettingsService::class)->updateSchedulingConfig([
        'check_student_cohort_conflicts' => true,
        'check_synchronous_online_conflicts' => false,
        'check_asynchronous_online_conflicts' => true,
    ]);

    $flippedSections = collect($this->actingAs($admin)->getJson($url)->assertOk()->json())->keyBy('section');
    expect($flippedSections->get('A-F2F')['schedules'][0]['checks_time_conflicts'])->toBeTrue()
        ->and($flippedSections->get('B-ASYNC')['schedules'][0]['checks_time_conflicts'])->toBeTrue()
        ->and($flippedSections->get('C-SYNC')['schedules'][0]['checks_time_conflicts'])->toBeFalse();

    // 3. Student/cohort conflict checking globally disabled: all sections exempt
    app(GeneralSettingsService::class)->updateSchedulingConfig([
        'check_student_cohort_conflicts' => false,
        'check_synchronous_online_conflicts' => true,
        'check_asynchronous_online_conflicts' => true,
    ]);

    $disabledSections = collect($this->actingAs($admin)->getJson($url)->assertOk()->json())->keyBy('section');
    expect($disabledSections->get('A-F2F')['schedules'][0]['checks_time_conflicts'])->toBeFalse()
        ->and($disabledSections->get('B-ASYNC')['schedules'][0]['checks_time_conflicts'])->toBeFalse()
        ->and($disabledSections->get('C-SYNC')['schedules'][0]['checks_time_conflicts'])->toBeFalse();
});
