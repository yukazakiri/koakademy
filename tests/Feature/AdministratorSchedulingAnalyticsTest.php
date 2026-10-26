<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

it('only includes active courses in scheduling class creation options', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $activeCourse = Course::factory()->create([
        'code' => 'ACTIVE',
        'is_active' => true,
    ]);

    $inactiveCourse = Course::factory()->create([
        'code' => 'INACTIVE',
        'is_active' => false,
    ]);

    $this->get(route('administrators.scheduling-analytics.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/scheduling-analytics', false)
            ->where('creation_options.courses', fn ($courses): bool => collect($courses)->contains('id', $activeCourse->id)
                && ! collect($courses)->contains('id', $inactiveCourse->id))
        );
});

it('exposes course-specific curriculum years for shared scheduling classes', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-22',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
    $hospitality = Course::factory()->create(['code' => 'BSHM', 'is_active' => true]);
    $business = Course::factory()->create(['code' => 'BSBA', 'is_active' => true]);
    $hospitalitySubject = Subject::factory()->for($hospitality)->create(['code' => 'FS-HM', 'academic_year' => 4]);
    $businessSubject = Subject::factory()->for($business)->create(['code' => 'FS-BA', 'academic_year' => 3]);
    $class = Classes::factory()->create([
        'subject_id' => $hospitalitySubject->id,
        'subject_ids' => [$hospitalitySubject->id, $businessSubject->id],
        'subject_code' => 'FS',
        'academic_year' => 1,
        'course_codes' => [$hospitality->id, (string) $business->id],
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'classification' => 'college',
    ]);
    Schedule::factory()->create(['class_id' => $class->id]);

    $this->get(route('administrators.scheduling-analytics.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('schedule_data.0.id', $class->id)
            ->where('schedule_data.0.year_levels', ['3rd Year', '4th Year'])
            ->where("schedule_data.0.course_year_levels.{$hospitality->id}", ['4th Year'])
            ->where("schedule_data.0.course_year_levels.{$business->id}", ['3rd Year'])
            ->where('filters.available_year_levels', ['3rd Year', '4th Year'])
        );
});

it('generates cohort offerings from curriculum and places recurring schedule patterns', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-22',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);

    $course = Course::factory()->create(['code' => 'BSIT', 'is_active' => true]);
    $room = App\Models\Room::factory()->create(['name' => 'Lab 101', 'capacity' => 45, 'type' => 'computer_lab', 'is_active' => true]);

    Subject::factory()->for($course)->create([
        'code' => 'IT 101',
        'title' => 'Intro to Computing',
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 2,
        'laboratory' => 3,
        'units' => 3,
    ]);

    Subject::factory()->for($course)->create([
        'code' => 'IT 102',
        'title' => 'Programming 1',
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 2,
        'laboratory' => 3,
        'units' => 3,
    ]);

    $genResponse = $this->postJson(route('administrators.scheduling-analytics.offerings.generate'), [
        'classification' => 'college',
        'school_year' => '2026 - 2027',
        'semester' => '1',
        'course_id' => $course->id,
        'academic_year' => 1,
        'sections' => ['A', 'B'],
        'maximum_slots' => 40,
        'room_id' => $room->id,
    ]);

    $genResponse->assertOk()
        ->assertJsonPath('created_count', 4)
        ->assertJsonPath('skipped_count', 0)
        ->assertJsonPath('subjects_found', 2);

    $firstClassId = (int) $genResponse->json('classes.0.id');
    expect($genResponse->json('classes.0.required_hours'))->toEqual(5)
        ->and($genResponse->json('classes.0.is_unscheduled'))->toBeTrue();

    // Place an MWF recurring pattern onto the first generated class
    $patternResponse = $this->postJson(route('administrators.scheduling-analytics.classes.schedules.store', ['class' => $firstClassId]), [
        'days' => ['Monday', 'Wednesday', 'Friday'],
        'start_time' => '08:00',
        'end_time' => '09:00',
        'room_id' => $room->id,
        'component_type' => 'lecture',
        'status' => 'draft',
    ]);

    $patternResponse->assertOk()
        ->assertJsonPath('class.id', $firstClassId)
        ->assertJsonPath('class.scheduled_hours', 3)
        ->assertJsonPath('class.is_unscheduled', false)
        ->assertJsonCount(3, 'class.schedules');

    $patternGroupId = $patternResponse->json('class.schedules.0.pattern_group_id');
    expect($patternGroupId)->not->toBeNull()
        ->and($patternResponse->json('class.schedules.1.pattern_group_id'))->toBe($patternGroupId);

    // Publish the draft blocks for the term
    $publishResponse = $this->postJson(route('administrators.scheduling-analytics.publish'), [
        'school_year' => '2026 - 2027',
        'semester' => '1',
    ]);

    $publishResponse->assertOk()
        ->assertJsonPath('published_count', 3);
});

it('detects section cohort conflicts when two subjects in the same course, year, and section overlap', function (): void {
    $course = Course::factory()->create(['code' => 'BSCS', 'is_active' => true]);
    $roomA = App\Models\Room::factory()->create(['name' => 'Room A']);
    $roomB = App\Models\Room::factory()->create(['name' => 'Room B']);
    $facultyA = App\Models\Faculty::factory()->create();
    $facultyB = App\Models\Faculty::factory()->create();

    $class1 = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'section' => 'A',
        'subject_code' => 'CS 101',
        'faculty_id' => $facultyA->id,
        'room_id' => $roomA->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);

    $class2 = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'section' => 'A',
        'subject_code' => 'MATH 101',
        'faculty_id' => $facultyB->id,
        'room_id' => $roomB->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);

    Schedule::factory()->create([
        'class_id' => $class1->id,
        'room_id' => $roomA->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:00',
        'end_time' => '11:30',
    ]);

    Schedule::factory()->create([
        'class_id' => $class2->id,
        'room_id' => $roomB->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:30',
        'end_time' => '12:00',
    ]);

    $classes = Classes::forAcademicPeriod('2026 - 2027', 1)
        ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
        ->get();

    $conflicts = app(App\Services\Scheduling\ScheduleConflictService::class)->detectConflictsForClasses($classes);

    expect($conflicts)->toHaveCount(1)
        ->and($conflicts[0]['conflict_type'])->toBe('section')
        ->and($conflicts[0]['class_1']['id'])->toBe($class1->id)
        ->and($conflicts[0]['class_2']['id'])->toBe($class2->id);
});

it('preserves schedule IDs on in-place sync and copies schedules when copying a class', function (): void {
    $room = App\Models\Room::factory()->create();
    $class = Classes::factory()->create([
        'section' => 'A',
        'room_id' => $room->id,
    ]);

    $originalSchedule = Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $schedulingService = app(App\Services\Scheduling\SchedulingService::class);

    $synced = $schedulingService->syncClassSchedules($class, [
        [
            'day_of_week' => 'Wednesday',
            'start_time' => '10:00',
            'end_time' => '11:30',
            'room_id' => $room->id,
        ],
    ]);

    expect($synced)->toHaveCount(1)
        ->and($synced->first()->id)->toBe($originalSchedule->id)
        ->and($synced->first()->day_of_week)->toBe('Wednesday');

    $targetClass = $class->replicate(['section']);
    $targetClass->section = 'B';
    $targetClass->save();

    $copied = $schedulingService->copyClassSchedules($class, $targetClass);

    expect($copied)->toHaveCount(1)
        ->and($copied->first()->class_id)->toBe($targetClass->id)
        ->and($copied->first()->day_of_week)->toBe('Wednesday');
});
