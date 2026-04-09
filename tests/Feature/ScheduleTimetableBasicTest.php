<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Pages\Timetable;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Carbon\Carbon;

it('allows authorized users to access schedule timetable', function () {
    $user = User::factory()->create(['role' => UserRole::Registrar]);

    $this->actingAs($user);

    expect(Timetable::canAccess())->toBeTrue();
});

it('denies access to unauthorized users', function () {
    $user = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($user);

    expect(Timetable::canAccess())->toBeFalse();
});

it('generates correct time slots', function () {
    $page = new Timetable();

    $timeSlots = $page->getTimeSlots();

    expect($timeSlots)->toContain('07:00');
    expect($timeSlots)->toContain('07:30');
    expect($timeSlots)->toContain('21:00');
    expect($timeSlots)->toContain('21:30');
    expect(count($timeSlots))->toBeGreaterThan(20);
});

it('provides different empty slot messages based on view type', function () {
    $page = new Timetable();

    $reflection = new ReflectionClass($page);
    $selectedViewProperty = $reflection->getProperty('selectedView');
    $selectedViewProperty->setAccessible(true);

    // Test room view
    $selectedViewProperty->setValue($page, 'room');
    expect($page->getEmptySlotMessage())->toBe('Room Available');

    // Test course view
    $selectedViewProperty->setValue($page, 'course');
    expect($page->getEmptySlotMessage())->toBe('No Class');

    // Test year level view
    $selectedViewProperty->setValue($page, 'year_level');
    expect($page->getEmptySlotMessage())->toBe('No Class');
});

it('generates correct timetable titles based on view', function () {
    $course = Course::factory()->create([
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
    ]);

    $room = Room::factory()->create(['name' => '401']);

    $page = new Timetable();
    $reflection = new ReflectionClass($page);

    $selectedViewProperty = $reflection->getProperty('selectedView');
    $selectedViewProperty->setAccessible(true);

    $selectedCourseProperty = $reflection->getProperty('selectedCourse');
    $selectedCourseProperty->setAccessible(true);

    $selectedRoomProperty = $reflection->getProperty('selectedRoom');
    $selectedRoomProperty->setAccessible(true);

    $selectedYearLevelProperty = $reflection->getProperty('selectedYearLevel');
    $selectedYearLevelProperty->setAccessible(true);

    // Test course view
    $selectedViewProperty->setValue($page, 'course');
    $selectedCourseProperty->setValue($page, (string) $course->id);
    expect($page->getTimetableTitle())
        ->toBe('Schedule Timetable - BSIT (Bachelor of Science in Information Technology)');

    // Test room view
    $selectedViewProperty->setValue($page, 'room');
    $selectedCourseProperty->setValue($page, null);
    $selectedRoomProperty->setValue($page, (string) $room->id);
    expect($page->getTimetableTitle())
        ->toBe('Schedule Timetable - Room 401');

    // Test year level view
    $selectedViewProperty->setValue($page, 'year_level');
    $selectedRoomProperty->setValue($page, null);
    $selectedYearLevelProperty->setValue($page, '3');
    expect($page->getTimetableTitle())
        ->toBe('Schedule Timetable - 3rd Year');
});

it('filters schedules by day and time correctly', function () {
    $course = Course::factory()->create(['is_active' => true]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $room = Room::factory()->create();
    $faculty = Faculty::factory()->create();

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    $schedule = Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => Carbon::createFromFormat('H:i', '08:00'),
        'end_time' => Carbon::createFromFormat('H:i', '09:30'),
    ]);

    $page = new Timetable();

    // Set up the schedule data
    $reflection = new ReflectionClass($page);
    $scheduleDataProperty = $reflection->getProperty('scheduleData');
    $scheduleDataProperty->setAccessible(true);
    $scheduleDataProperty->setValue($page, collect([$schedule]));

    // Test filtering
    $mondaySchedules = $page->getScheduleForDayAndTime('Monday', '08:30');
    expect($mondaySchedules)->toHaveCount(1);
    expect($mondaySchedules->first()->id)->toBe($schedule->id);

    // Test non-matching time
    $tuesdaySchedules = $page->getScheduleForDayAndTime('Tuesday', '08:30');
    expect($tuesdaySchedules)->toHaveCount(0);

    // Test non-matching time on correct day
    $wrongTimeSchedules = $page->getScheduleForDayAndTime('Monday', '10:30');
    expect($wrongTimeSchedules)->toHaveCount(0);
});

it('generates correct schedule card data', function () {
    $course = Course::factory()->create([
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
        'is_active' => true,
    ]);

    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT101',
        'title' => 'Introduction to Computing',
    ]);

    $room = Room::factory()->create(['name' => '401']);
    $faculty = Faculty::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'middle_name' => null,
    ]);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'course_codes' => [$course->id],
        'section' => 'A',
        'maximum_slots' => 40,
        'school_year' => '2024 - 2025',
        'semester' => 1,
    ]);

    $schedule = Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Friday',
        'start_time' => Carbon::createFromFormat('H:i', '14:00'),
        'end_time' => Carbon::createFromFormat('H:i', '15:30'),
    ]);

    $page = new Timetable();
    $cardData = $page->getScheduleCardData($schedule);

    expect($cardData)->toHaveKeys([
        'subject', 'faculty', 'room', 'time', 'section',
        'course_codes', 'student_count', 'max_slots', 'class_id',
    ]);

    expect($cardData['subject'])->toBe('Introduction to Computing');
    expect($cardData['faculty'])->toBe('Doe, John');
    expect($cardData['room'])->toBe('401');
    expect($cardData['section'])->toBe('A');
    expect($cardData['max_slots'])->toBe(40);
    expect($cardData['class_id'])->toBe($class->id);
});

it('resets filters correctly when view changes', function () {
    $page = new Timetable();

    $reflection = new ReflectionClass($page);

    $selectedCourseProperty = $reflection->getProperty('selectedCourse');
    $selectedCourseProperty->setAccessible(true);

    $selectedRoomProperty = $reflection->getProperty('selectedRoom');
    $selectedRoomProperty->setAccessible(true);

    $selectedYearLevelProperty = $reflection->getProperty('selectedYearLevel');
    $selectedYearLevelProperty->setAccessible(true);

    // Set some values
    $selectedCourseProperty->setValue($page, '1');
    $selectedRoomProperty->setValue($page, '2');
    $selectedYearLevelProperty->setValue($page, '3');

    // Call reset filters
    $method = $reflection->getMethod('resetFilters');
    $method->setAccessible(true);
    $method->invoke($page);

    // Check all are reset
    expect($selectedCourseProperty->getValue($page))->toBeNull();
    expect($selectedRoomProperty->getValue($page))->toBeNull();
    expect($selectedYearLevelProperty->getValue($page))->toBeNull();
});
