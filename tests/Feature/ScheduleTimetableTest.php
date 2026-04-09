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
use Livewire\Livewire;

it('can access schedule timetable page with proper authorization', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);

    $this->actingAs($user);

    expect(Timetable::canAccess())->toBeTrue();

    $this->get(Timetable::getUrl())
        ->assertSuccessful();
});

it('cannot access schedule timetable page without proper authorization', function () {
    $user = User::factory()->create(['role' => UserRole::Student]);

    $this->actingAs($user);

    expect(Timetable::canAccess())->toBeFalse();
});

it('initializes with current academic year and semester', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

    $generalSettingsService = app(App\Services\GeneralSettingsService::class);
    $expectedAcademicYear = $generalSettingsService->getCurrentSchoolYearString();
    $expectedSemester = (string) $generalSettingsService->getCurrentSemester();

    Livewire::test(Timetable::class)
        ->assertSet('selectedAcademicYear', $expectedAcademicYear)
        ->assertSet('selectedSemester', $expectedSemester);
});

it('can filter by course view', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

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
    $faculty = Faculty::factory()->create();

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'semester' => 1,
        'school_year' => '2024 - 2025',
        'section' => 'A',
        'classification' => 'college',
    ]);

    $schedule = Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => Carbon::createFromFormat('H:i', '08:00'),
        'end_time' => Carbon::createFromFormat('H:i', '09:30'),
    ]);

    Livewire::test(Timetable::class)
        ->set('selectedView', 'course')
        ->set('selectedCourse', (string) $course->id)
        ->set('selectedAcademicYear', '2024 - 2025')
        ->set('selectedSemester', '1')
        ->call('loadScheduleData')
        ->assertSet('scheduleData', function ($data) use ($schedule) {
            return $data->contains('id', $schedule->id);
        });
});

it('can filter by room view', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

    $room = Room::factory()->create(['name' => '401']);
    $course = Course::factory()->create(['is_active' => true]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
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
        'day_of_week' => 'Tuesday',
        'start_time' => Carbon::createFromFormat('H:i', '10:00'),
        'end_time' => Carbon::createFromFormat('H:i', '11:30'),
    ]);

    Livewire::test(Timetable::class)
        ->set('selectedView', 'room')
        ->set('selectedRoom', (string) $room->id)
        ->set('selectedAcademicYear', '2024 - 2025')
        ->set('selectedSemester', '1')
        ->call('loadScheduleData')
        ->assertSet('scheduleData', function ($data) use ($schedule) {
            return $data->contains('id', $schedule->id);
        });
});

it('can filter by year level view', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

    $course = Course::factory()->create(['is_active' => true]);
    $subject = Subject::factory()->create(['course_id' => $course->id]);
    $room = Room::factory()->create();
    $faculty = Faculty::factory()->create();

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'academic_year' => 2, // 2nd year
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ]);

    $schedule = Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Wednesday',
        'start_time' => Carbon::createFromFormat('H:i', '13:00'),
        'end_time' => Carbon::createFromFormat('H:i', '14:30'),
    ]);

    Livewire::test(Timetable::class)
        ->set('selectedView', 'year_level')
        ->set('selectedYearLevel', '2')
        ->set('selectedAcademicYear', '2024 - 2025')
        ->set('selectedSemester', '1')
        ->call('loadScheduleData')
        ->assertSet('scheduleData', function ($data) use ($schedule) {
            return $data->contains('id', $schedule->id);
        });
});

it('can get schedule for specific day and time', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

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
        'day_of_week' => 'Thursday',
        'start_time' => Carbon::createFromFormat('H:i', '08:00'),
        'end_time' => Carbon::createFromFormat('H:i', '09:30'),
    ]);

    $component = Livewire::test(ScheduleTimetable::class)
        ->set('selectedAcademicYear', '2024 - 2025')
        ->set('selectedSemester', '1')
        ->call('loadScheduleData');

    $daySchedules = $component->instance()->getScheduleForDayAndTime('Thursday', '08:30');

    expect($daySchedules)->toHaveCount(1);
    expect($daySchedules->first()->id)->toBe($schedule->id);
});

it('generates time slots correctly', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

    $component = Livewire::test(Timetable::class);

    $timeSlots = $component->instance()->getTimeSlots();

    expect($timeSlots)->toContain('07:00');
    expect($timeSlots)->toContain('07:30');
    expect($timeSlots)->toContain('21:00');
    expect($timeSlots)->toContain('21:30');
});

it('can get schedule card data', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

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
    $faculty = Faculty::factory()->create(['full_name' => 'John Doe']);

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

    $component = Livewire::test(ScheduleTimetable::class);
    $cardData = $component->instance()->getScheduleCardData($schedule);

    expect($cardData)->toHaveKeys([
        'subject', 'faculty', 'room', 'time', 'section',
        'course_codes', 'student_count', 'max_slots', 'class_id',
    ]);

    expect($cardData['subject'])->toBe('Introduction to Computing');
    expect($cardData['faculty'])->toBe('John Doe');
    expect($cardData['room'])->toBe('401');
    expect($cardData['section'])->toBe('A');
    expect($cardData['max_slots'])->toBe(40);
});

it('displays proper timetable title based on view', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

    $course = Course::factory()->create([
        'code' => 'BSIT',
        'title' => 'Bachelor of Science in Information Technology',
    ]);

    $room = Room::factory()->create(['name' => '401']);

    // Test course view
    $component = Livewire::test(ScheduleTimetable::class)
        ->set('selectedView', 'course')
        ->set('selectedCourse', (string) $course->id);

    expect($component->instance()->getTimetableTitle())
        ->toBe('Schedule Timetable - BSIT (Bachelor of Science in Information Technology)');

    // Test room view
    $component->set('selectedView', 'room')
        ->set('selectedRoom', (string) $room->id);

    expect($component->instance()->getTimetableTitle())
        ->toBe('Schedule Timetable - Room 401');

    // Test year level view
    $component->set('selectedView', 'year_level')
        ->set('selectedYearLevel', '3');

    expect($component->instance()->getTimetableTitle())
        ->toBe('Schedule Timetable - 3rd Year');
});

it('provides appropriate empty slot messages', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

    $component = Livewire::test(ScheduleTimetable::class);

    // Test room view
    $component->set('selectedView', 'room');
    expect($component->instance()->getEmptySlotMessage())->toBe('Room Available');

    // Test course view
    $component->set('selectedView', 'course');
    expect($component->instance()->getEmptySlotMessage())->toBe('No Class');

    // Test year level view
    $component->set('selectedView', 'year_level');
    expect($component->instance()->getEmptySlotMessage())->toBe('No Class');
});

it('resets filters when changing view type', function () {

    $user = User::factory()->create(['role' => UserRole::Registrar]);
    $this->actingAs($user);

    $course = Course::factory()->create();
    $room = Room::factory()->create();

    Livewire::test(Timetable::class)
        ->set('selectedView', 'course')
        ->set('selectedCourse', (string) $course->id)
        ->set('selectedView', 'room')
        ->assertSet('selectedCourse', null)
        ->set('selectedRoom', (string) $room->id)
        ->set('selectedView', 'year_level')
        ->assertSet('selectedRoom', null);
});
