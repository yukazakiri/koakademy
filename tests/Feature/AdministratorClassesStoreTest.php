<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id'], 'activity_log_subject_index');
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->index(['causer_type', 'causer_id'], 'activity_log_causer_index');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }
});

it('stores a college class with schedules from the administrator page payload', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $course = Course::factory()->create();
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT101',
        'title' => 'Programming Fundamentals',
    ]);
    $faculty = Faculty::factory()->create();
    $room = Room::factory()->create([
        'is_active' => true,
    ]);

    $response = $this->post(route('administrators.classes.store'), [
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => 'IT101',
        'academic_year' => 1,
        'shs_track_id' => '',
        'shs_strand_id' => '',
        'subject_code_shs' => '',
        'grade_level' => '',
        'faculty_id' => $faculty->id,
        'semester' => '1',
        'school_year' => '2026 - 2027',
        'section' => 'A',
        'room_id' => $room->id,
        'maximum_slots' => 40,
        'schedules' => [
            [
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:00',
                'room_id' => $room->id,
            ],
        ],
        'settings' => [
            'background_color' => '#ffffff',
            'accent_color' => '#3b82f6',
            'theme' => 'default',
            'enable_announcements' => true,
            'enable_grade_visibility' => true,
            'enable_attendance_tracking' => false,
            'allow_late_submissions' => false,
            'enable_discussion_board' => false,
            'custom' => [],
        ],
    ]);

    $response
        ->assertRedirect(route('administrators.classes.index'))
        ->assertSessionHas('flash.message', 'Class created successfully.');

    $class = Classes::query()->latest('id')->first();

    expect($class)->not->toBeNull();
    expect($class?->classification)->toBe('college');
    expect($class?->subject_id)->toBe($subject->id);
    expect($class?->section)->toBe('A');

    $schedule = Schedule::query()->where('class_id', $class?->id)->first();

    expect($schedule)->not->toBeNull();
    expect($schedule?->day_of_week)->toBe('Monday');
    expect($schedule?->start_time?->format('H:i'))->toBe('08:00');
    expect($schedule?->end_time?->format('H:i'))->toBe('09:00');
    expect($schedule?->room_id)->toBe($room->id);
});

it('renders the administrator class edit page with existing class data', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $course = Course::factory()->create();
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT202',
        'title' => 'Data Structures',
    ]);
    $faculty = Faculty::factory()->create();
    $room = Room::factory()->create(['is_active' => true]);

    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => 'IT202',
        'academic_year' => 2,
        'faculty_id' => $faculty->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'section' => 'B',
        'room_id' => $room->id,
        'maximum_slots' => 35,
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'accent_color' => '#ef4444',
            'enable_attendance_tracking' => true,
        ]),
    ]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:00',
        'end_time' => '11:30',
    ]);

    $this->get(route('administrators.classes.edit', $class))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/create', false)
            ->where('mode', 'edit')
            ->where('class_id', $class->id)
            ->where('defaults.classification', 'college')
            ->where('defaults.course_codes.0', $course->id)
            ->where('defaults.subject_ids.0', $subject->id)
            ->where('defaults.subject_code', 'IT202')
            ->where('defaults.academic_year', 2)
            ->where('defaults.faculty_id', (string) $faculty->id)
            ->where('defaults.section', 'B')
            ->where('defaults.maximum_slots', 35)
            ->where('defaults.schedules.0.day_of_week', 'Tuesday')
            ->where('defaults.schedules.0.start_time', '10:00')
            ->where('defaults.schedules.0.end_time', '11:30')
            ->where('defaults.settings.accent_color', '#ef4444')
            ->where('defaults.settings.enable_attendance_tracking', true)
        );
});

it('shows active old curriculum courses on the administrator class edit page even when they are not enrollment courses', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $oldCourse = Course::factory()->create([
        'code' => 'BSITWEB18',
        'curriculum_year' => '2018 - 2019',
        'is_active' => true,
    ]);
    $newCourse = Course::factory()->create([
        'code' => 'BSITWEB24',
        'curriculum_year' => '2024 - 2025',
        'is_active' => true,
    ]);

    GeneralSetting::factory()->create([
        'enrollment_courses' => [$newCourse->id],
    ]);

    $subject = Subject::factory()->create([
        'course_id' => $oldCourse->id,
        'code' => 'WEB101',
        'title' => 'Web Development Fundamentals',
    ]);

    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$oldCourse->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => 'WEB101',
        'academic_year' => 1,
    ]);

    $this->get(route('administrators.classes.edit', $class))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/create', false)
            ->where('mode', 'edit')
            ->where('defaults.course_codes.0', $oldCourse->id)
            ->where('options.courses.0.id', $oldCourse->id)
            ->where('options.courses.0.label', 'BSITWEB18 (2018 - 2019)')
            ->where('options.courses.1.id', $newCourse->id)
            ->where('options.courses.1.label', 'BSITWEB24 (2024 - 2025)')
        );
});

it('rejects administrator class schedule updates that conflict with another class', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $course = Course::factory()->create();
    $subject = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT303',
        'title' => 'Systems Analysis',
    ]);
    $faculty = Faculty::factory()->create();
    $room = Room::factory()->create(['is_active' => true]);

    $conflictingClass = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => 'IT303',
        'academic_year' => 3,
        'faculty_id' => $faculty->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'section' => 'A',
        'room_id' => $room->id,
    ]);

    Schedule::factory()->create([
        'class_id' => $conflictingClass->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => 'IT303',
        'academic_year' => 3,
        'faculty_id' => $faculty->id,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'section' => 'B',
        'room_id' => $room->id,
    ]);

    $currentSchedule = Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '13:00',
        'end_time' => '14:00',
    ]);

    $response = $this->patch(route('administrators.classes.update', $class), [
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => 'IT303',
        'academic_year' => 3,
        'faculty_id' => $faculty->id,
        'semester' => '1',
        'school_year' => '2026 - 2027',
        'section' => 'B',
        'room_id' => $room->id,
        'maximum_slots' => 40,
        'schedules' => [
            [
                'day_of_week' => 'Monday',
                'start_time' => '09:00',
                'end_time' => '10:30',
                'room_id' => $room->id,
            ],
        ],
        'settings' => [
            'background_color' => '#ffffff',
            'accent_color' => '#3b82f6',
            'theme' => 'default',
            'enable_announcements' => true,
            'enable_grade_visibility' => true,
            'enable_attendance_tracking' => false,
            'allow_late_submissions' => false,
            'enable_discussion_board' => false,
            'custom' => [],
        ],
    ]);

    $response
        ->assertRedirect()
        ->assertInvalid(['schedules' => 'conflicts with another class']);

    $currentSchedule->refresh();

    expect($currentSchedule->day_of_week)->toBe('Wednesday');
    expect($currentSchedule->start_time->format('H:i'))->toBe('13:00');
    expect($currentSchedule->end_time->format('H:i'))->toBe('14:00');
});

describe('class schedule form integration', function () {
    it('stores schedule metadata and nullable rooms through the administrator create form', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $course = Course::factory()->create();
        $subject = Subject::factory()->create(['course_id' => $course->id]);
        $room = Room::factory()->create(['is_active' => true]);

        $this->post(route('administrators.classes.store'), [
            'classification' => 'college',
            'course_codes' => [$course->id],
            'subject_ids' => [$subject->id],
            'subject_code' => $subject->code,
            'academic_year' => 1,
            'faculty_id' => null,
            'semester' => '1',
            'school_year' => '2026 - 2027',
            'section' => 'A',
            'room_id' => null,
            'maximum_slots' => 32,
            'schedules' => [
                [
                    'day_of_week' => 'Monday',
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'room_id' => null,
                    'component_type' => 'lab',
                    'pattern_group_id' => 'lab-monday-wednesday',
                    'status' => 'draft',
                    'subject_id' => $subject->id,
                ],
                [
                    'day_of_week' => 'Wednesday',
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'room_id' => $room->id,
                    'component_type' => 'lab',
                    'pattern_group_id' => 'lab-monday-wednesday',
                    'status' => 'published',
                    'subject_id' => $subject->id,
                ],
            ],
        ])->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

        $class = Classes::query()->sole();
        expect($class->room_id)->toBeNull();
        expect($class->schedules()->count())->toBe(2);
        $this->assertDatabaseHas('schedule', [
            'class_id' => $class->id,
            'day_of_week' => 'Monday',
            'room_id' => null,
            'component_type' => 'lab',
            'pattern_group_id' => 'lab-monday-wednesday',
            'status' => 'draft',
            'subject_id' => $subject->id,
        ]);
        $this->assertDatabaseHas('schedule', [
            'class_id' => $class->id,
            'day_of_week' => 'Wednesday',
            'room_id' => $room->id,
            'component_type' => 'lab',
            'pattern_group_id' => 'lab-monday-wednesday',
            'status' => 'published',
            'subject_id' => $subject->id,
        ]);
    });

    it('serializes schedule identities and metadata without assigning an available room', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $class = Classes::factory()->create(['classification' => 'college', 'room_id' => null]);
        Room::factory()->create(['is_active' => true]);
        $schedule = Schedule::factory()->create([
            'class_id' => $class->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'room_id' => null,
            'component_type' => 'lab',
            'pattern_group_id' => 'laboratory-pattern',
            'status' => 'draft',
            'subject_id' => $class->subject_id,
        ]);

        $this->get(route('administrators.classes.edit', $class))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('administrators/classes/create', false)
                ->where('defaults.room_id', null)
                ->where('defaults.schedules.0.id', $schedule->id)
                ->where('defaults.schedules.0.day_of_week', 'Tuesday')
                ->where('defaults.schedules.0.start_time', '10:00')
                ->where('defaults.schedules.0.end_time', '12:00')
                ->where('defaults.schedules.0.room_id', null)
                ->where('defaults.schedules.0.component_type', 'lab')
                ->where('defaults.schedules.0.pattern_group_id', 'laboratory-pattern')
                ->where('defaults.schedules.0.status', 'draft')
                ->where('defaults.schedules.0.subject_id', $class->subject_id)
            );
    });

    it('updates metadata in place when identified schedule rows are reordered', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $class = Classes::factory()->create(['classification' => 'college', 'faculty_id' => null]);
        $first = Schedule::factory()->create([
            'class_id' => $class->id,
            'room_id' => $class->room_id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'component_type' => 'lecture',
            'status' => 'published',
        ]);
        $second = Schedule::factory()->create([
            'class_id' => $class->id,
            'room_id' => $class->room_id,
            'day_of_week' => 'Thursday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'component_type' => 'lecture',
            'status' => 'published',
        ]);

        $this->patch(route('administrators.classes.update', $class), [
            'schedules' => [
                [
                    'id' => (string) $second->id,
                    'day_of_week' => 'Tuesday',
                    'start_time' => '13:00',
                    'end_time' => '15:00',
                    'room_id' => null,
                    'component_type' => 'lab',
                    'pattern_group_id' => 'updated-laboratory',
                    'status' => 'draft',
                    'subject_id' => $class->subject_id,
                ],
                [
                    'id' => $first->id,
                    'day_of_week' => 'Wednesday',
                    'start_time' => '09:00',
                    'end_time' => '10:00',
                    'room_id' => $class->room_id,
                    'component_type' => 'lecture',
                    'pattern_group_id' => 'updated-lecture',
                    'status' => 'published',
                    'subject_id' => null,
                ],
            ],
        ])->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

        expect($class->schedules()->count())->toBe(2);
        expect($class->schedules()->withTrashed()->count())->toBe(2);
        $this->assertDatabaseHas('schedule', [
            'id' => $second->id,
            'class_id' => $class->id,
            'day_of_week' => 'Tuesday',
            'room_id' => null,
            'component_type' => 'lab',
            'pattern_group_id' => 'updated-laboratory',
            'status' => 'draft',
            'subject_id' => $class->subject_id,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('schedule', [
            'id' => $first->id,
            'class_id' => $class->id,
            'day_of_week' => 'Wednesday',
            'component_type' => 'lecture',
            'pattern_group_id' => 'updated-lecture',
            'status' => 'published',
            'subject_id' => null,
            'deleted_at' => null,
        ]);
        expect($second->fresh()->end_time->format('H:i'))->toBe('15:00');
        expect($first->fresh()->start_time->format('H:i'))->toBe('09:00');
    });

    it('rejects duplicate schedule ids before changing the class or its sessions', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $class = Classes::factory()->create(['classification' => 'college', 'faculty_id' => null, 'maximum_slots' => 32]);
        $schedule = Schedule::factory()->create([
            'class_id' => $class->id,
            'room_id' => $class->room_id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
        ]);

        $this->patch(route('administrators.classes.update', $class), [
            'maximum_slots' => 48,
            'schedules' => [
                ['id' => $schedule->id, 'day_of_week' => 'Tuesday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => null],
                ['id' => (string) $schedule->id, 'day_of_week' => 'Wednesday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => null],
            ],
        ])->assertInvalid(['schedules.0.id', 'schedules.1.id']);

        expect($class->fresh()->maximum_slots)->toBe(32);
        expect($class->schedules()->withTrashed()->count())->toBe(1);
        expect($schedule->fresh()->day_of_week)->toBe('Monday');
    });

    it('rejects schedule ids owned by another class before changing either class', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $class = Classes::factory()->create(['classification' => 'college', 'faculty_id' => null, 'maximum_slots' => 32]);
        $schedule = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday']);
        $foreignSchedule = Schedule::factory()->create(['day_of_week' => 'Friday']);

        $this->patch(route('administrators.classes.update', $class), [
            'maximum_slots' => 48,
            'schedules' => [
                ['id' => $foreignSchedule->id, 'day_of_week' => 'Tuesday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => null],
            ],
        ])->assertInvalid(['schedules.0.id']);

        expect($class->fresh()->maximum_slots)->toBe(32);
        expect($class->schedules()->withTrashed()->count())->toBe(1);
        expect($schedule->fresh()->day_of_week)->toBe('Monday');
        expect($foreignSchedule->fresh()->day_of_week)->toBe('Friday');
        expect($foreignSchedule->fresh()->class_id)->not->toBe($class->id);
    });

    it('preserves existing identities and metadata for legacy schedule updates without ids', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $class = Classes::factory()->create(['classification' => 'college', 'faculty_id' => null]);
        $schedule = Schedule::factory()->create([
            'class_id' => $class->id,
            'day_of_week' => 'Monday',
            'room_id' => $class->room_id,
            'component_type' => 'lab',
            'pattern_group_id' => 'legacy-pattern',
            'status' => 'draft',
            'subject_id' => $class->subject_id,
        ]);

        $this->patch(route('administrators.classes.update', $class), [
            'schedules' => [
                ['day_of_week' => 'Wednesday', 'start_time' => '13:00', 'end_time' => '15:00', 'room_id' => null],
            ],
        ])->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

        expect($class->schedules()->withTrashed()->count())->toBe(1);
        $this->assertDatabaseHas('schedule', [
            'id' => $schedule->id,
            'class_id' => $class->id,
            'day_of_week' => 'Wednesday',
            'room_id' => null,
            'component_type' => 'lab',
            'pattern_group_id' => 'legacy-pattern',
            'status' => 'draft',
            'subject_id' => $class->subject_id,
            'deleted_at' => null,
        ]);
    });

    it('rejects supplied schedule ids when creating a new class', function () {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $existing = Schedule::factory()->create(['day_of_week' => 'Tuesday']);
        $course = Course::factory()->create();
        $subject = Subject::factory()->create(['course_id' => $course->id]);
        $classCount = Classes::query()->count();

        $this->post(route('administrators.classes.store'), [
            'classification' => 'college',
            'course_codes' => [$course->id],
            'subject_ids' => [$subject->id],
            'subject_code' => $subject->code,
            'academic_year' => 1,
            'faculty_id' => null,
            'semester' => '1',
            'school_year' => '2026 - 2027',
            'section' => 'A',
            'room_id' => null,
            'maximum_slots' => 32,
            'schedules' => [
                ['id' => $existing->id, 'day_of_week' => 'Tuesday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => null],
            ],
        ])->assertInvalid(['schedules.0.id']);

        expect(Classes::query()->count())->toBe($classCount);
        expect($existing->fresh()->day_of_week)->toBe('Tuesday');
        expect(Schedule::query()->count())->toBe(1);
    });
})->group('class-schedule-form-integration');

it('stores an online class without a room and strips rooms from its sessions', function () {
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($user);

    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT404']);
    $room = Room::factory()->create(['is_active' => true]);

    $this->post(route('administrators.classes.store'), [
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => 'IT404',
        'academic_year' => 1,
        'delivery_mode' => 'online',
        'semester' => '1',
        'school_year' => '2026 - 2027',
        'section' => 'A',
        'room_id' => $room->id,
        'maximum_slots' => 40,
        'schedules' => [
            ['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id],
        ],
        'settings' => ['theme' => 'default', 'custom' => []],
    ])->assertRedirect(route('administrators.classes.index'));

    $class = Classes::query()->latest('id')->firstOrFail();

    expect($class->delivery_mode)->toBe('online')
        ->and($class->room_id)->toBeNull()
        ->and(Schedule::query()->where('class_id', $class->id)->count())->toBe(1)
        ->and(Schedule::query()->where('class_id', $class->id)->value('room_id'))->toBeNull();
});

it('defaults to face to face and frees rooms when a class is switched to online', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $room = Room::factory()->create(['is_active' => true]);
    $class = Classes::factory()->create(['room_id' => $room->id]);
    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    expect($class->fresh()->delivery_mode)->toBe('face_to_face');

    $class->update(['delivery_mode' => 'online']);

    expect($class->fresh()->room_id)->toBeNull()
        ->and(Schedule::query()->where('class_id', $class->id)->value('room_id'))->toBeNull();
});

it('does not report a room clash between two online classes in the same slot', function () {
    $facultyA = Faculty::factory()->create();
    $facultyB = Faculty::factory()->create();

    $a = Classes::factory()->create(['delivery_mode' => 'online', 'faculty_id' => $facultyA->id, 'section' => 'A']);
    $b = Classes::factory()->create(['delivery_mode' => 'online', 'faculty_id' => $facultyB->id, 'section' => 'B']);

    foreach ([$a, $b] as $class) {
        Schedule::factory()->create([
            'class_id' => $class->id,
            'room_id' => null,
            'day_of_week' => 'Friday',
            'start_time' => '13:00',
            'end_time' => '14:30',
        ]);
    }

    $classes = Classes::query()->whereKey([$a->id, $b->id])->with(['Faculty', 'Schedule.room'])->get();

    expect(app(App\Services\Scheduling\ScheduleConflictService::class)->detectConflictsForClasses($classes))->toBe([]);
});
