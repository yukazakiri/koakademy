<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Scheduling\SchedulingService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;

it('loads existing sibling rooms and meetings into the batch blueprint', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create();
    $class = Classes::factory()->create([
        'classification' => 'college', 'subject_code' => 'BLUEPRINT-101',
        'school_year' => '2024 - 2025', 'semester' => 1, 'section' => 'A',
    ]);
    $sibling = Classes::factory()->create([
        'classification' => 'college', 'subject_id' => $class->subject_id,
        'subject_code' => $class->subject_code, 'school_year' => $class->school_year,
        'semester' => $class->semester, 'section' => 'B', 'room_id' => $room->id,
        'maximum_slots' => 0,
    ]);
    $meeting = Schedule::factory()->create([
        'class_id' => $sibling->id, 'room_id' => $room->id, 'day_of_week' => 'Tuesday',
        'start_time' => '08:00', 'end_time' => '09:00', 'status' => 'draft',
    ]);

    $this->get(route('administrators.classes.create', ['mode' => 'batch', 'blueprint_class_id' => $class->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/create', false)
            ->where('blueprint_sections', function ($sections) use ($sibling, $meeting, $room): bool {
                $row = collect($sections)->firstWhere('class_id', $sibling->id);

                return $row !== null && $row['maximum_slots'] === 0 && $row['room_id'] === $room->id
                    && collect($row['schedules'])->contains(fn ($schedule): bool => $schedule['id'] === $meeting->id && $schedule['status'] === 'draft');
            })
        );
});

it('rechecks schedule conflicts after acquiring the class creation lock', function (): void {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('This test intercepts the SQLite scheduling lock query.');
    }

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['is_active' => true]);
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 1]);
    $other = Classes::factory()->create(['school_year' => '2026 - 2027', 'semester' => 1]);
    $injected = false;
    DB::listen(function (QueryExecuted $query) use (&$injected, $other, $room): void {
        if ($injected || ! str_starts_with(mb_strtolower($query->sql), 'update "migrations"')) {
            return;
        }
        $injected = true;
        Schedule::factory()->create([
            'class_id' => $other->id, 'room_id' => $room->id, 'day_of_week' => 'Monday',
            'start_time' => '08:00', 'end_time' => '09:00', 'status' => 'published',
        ]);
    });

    $response = $this->post(route('administrators.classes.store'), [
        'classification' => 'college', 'course_codes' => [$course->id], 'subject_ids' => [$subject->id],
        'subject_code' => $subject->code, 'academic_year' => 1, 'semester' => '1',
        'school_year' => '2026 - 2027', 'section' => 'A', 'maximum_slots' => 40,
        'schedules' => [[
            'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id,
        ]],
    ]);

    $response->assertSessionHasErrors('schedules');
    expect($injected)->toBeTrue();
    expect(Classes::query()->where('subject_code', $subject->code)->exists())->toBeFalse();
});

it('refuses to publish draft meetings that conflict with another class', function (): void {
    $room = Room::factory()->create();
    $class = Classes::factory()->create(['semester' => 1, 'school_year' => '2026 - 2027']);
    $other = Classes::factory()->create(['semester' => 1, 'school_year' => '2026 - 2027']);
    $meeting = Schedule::factory()->create([
        'class_id' => $class->id, 'room_id' => $room->id, 'day_of_week' => 'Monday',
        'start_time' => '08:00', 'end_time' => '09:00', 'status' => 'draft',
    ]);
    Schedule::factory()->create([
        'class_id' => $other->id, 'room_id' => $room->id, 'day_of_week' => 'Monday',
        'start_time' => '08:00', 'end_time' => '09:00', 'status' => 'published',
    ]);

    expect(fn () => app(SchedulingService::class)->publishDraftSchedules($class))->toThrow(ValidationException::class);
    expect($meeting->fresh()->status)->toBe('draft');
});

it('rechecks schedule conflicts after acquiring the class update lock and preserves the banner', function (bool $removeBanner): void {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('This test intercepts the SQLite scheduling lock query.');
    }

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Storage::fake('public');
    $bannerPath = 'class-banners/existing.png';
    Storage::disk('public')->put($bannerPath, 'existing banner');
    $room = Room::factory()->create(['is_active' => true]);
    $class = Classes::factory()->create([
        'school_year' => '2026 - 2027', 'semester' => 1,
        'settings' => array_merge(Classes::getDefaultSettings(), ['banner_image' => $bannerPath]),
    ]);
    $other = Classes::factory()->create(['school_year' => $class->school_year, 'semester' => $class->semester]);
    $meeting = Schedule::factory()->create([
        'class_id' => $class->id, 'room_id' => $room->id, 'day_of_week' => 'Tuesday',
        'start_time' => '10:00', 'end_time' => '11:00',
    ]);
    $injected = false;
    DB::listen(function (QueryExecuted $query) use (&$injected, $other, $room): void {
        if ($injected || ! str_starts_with(mb_strtolower($query->sql), 'update "migrations"')) {
            return;
        }
        $injected = true;
        Schedule::factory()->create([
            'class_id' => $other->id, 'room_id' => $room->id, 'day_of_week' => 'Monday',
            'start_time' => '08:00', 'end_time' => '09:00', 'status' => 'published',
        ]);
    });

    $response = $this->patch(route('administrators.classes.update', $class), [
        'schedules' => [[
            'id' => $meeting->id, 'day_of_week' => 'Monday', 'start_time' => '08:00',
            'end_time' => '09:00', 'room_id' => $room->id,
        ]],
        ...($removeBanner ? ['remove_banner_image' => true] : ['settings' => ['banner_image' => UploadedFile::fake()->image('replacement.png')]]),
    ]);

    $response->assertSessionHasErrors('schedules');
    expect($injected)->toBeTrue();
    expect($meeting->fresh()->day_of_week)->toBe('Tuesday');
    expect($class->fresh()->getSetting('banner_image'))->toBe($bannerPath);
    Storage::disk('public')->assertExists($bannerPath);
    expect(Storage::disk('public')->allFiles('class-banners'))->toBe([$bannerPath]);
})->with(['remove' => true, 'replace' => false]);

it('rechecks the enrollment count after acquiring the class update lock', function (): void {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('This test intercepts the SQLite scheduling lock query.');
    }

    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create(['maximum_slots' => 10]);
    $students = Student::factory()->count(3)->create();
    $injected = false;
    DB::listen(function (QueryExecuted $query) use (&$injected, $class, $students): void {
        if ($injected || ! str_starts_with(mb_strtolower($query->sql), 'update "migrations"')) {
            return;
        }
        $injected = true;
        foreach ($students as $student) {
            ClassEnrollment::factory()->create([
                'class_id' => $class->id, 'student_id' => $student->id, 'status' => true,
                'enrollment_status' => ClassEnrollment::STATUS_ENROLLED,
            ]);
        }
    });

    $response = $this->patch(route('administrators.classes.update', $class), ['maximum_slots' => 2]);

    $response->assertSessionHasErrors('maximum_slots');
    expect($injected)->toBeTrue();
    expect($class->fresh()->maximum_slots)->toBe(10);
});
