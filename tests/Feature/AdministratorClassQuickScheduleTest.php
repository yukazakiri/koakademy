<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use App\Services\Scheduling\SchedulingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
        });
    }
});

it('updates only schedules, preserves meeting identities and metadata, and returns to the record', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['is_active' => true]);
    $class = Classes::factory()->create(['school_year' => '2026 - 2027', 'semester' => 1, 'maximum_slots' => 35]);
    $kept = Schedule::factory()->create([
        'class_id' => $class->id, 'room_id' => $room->id, 'day_of_week' => 'Monday',
        'start_time' => '08:00', 'end_time' => '09:00', 'component_type' => 'lab',
        'pattern_group_id' => 'pattern-123', 'status' => 'draft',
    ]);
    $removed = Schedule::factory()->create([
        'class_id' => $class->id, 'room_id' => $room->id, 'day_of_week' => 'Wednesday',
        'start_time' => '08:00', 'end_time' => '09:00',
    ]);

    $this->patch(route('administrators.classes.schedule.update', $class), [
        'schedule_revision' => app(SchedulingService::class)->revision($class),
        'maximum_slots' => 1,
        'schedules' => [
            ['id' => $kept->id, 'day_of_week' => 'Monday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => $room->id, 'delivery_mode' => 'face_to_face'],
            ['day_of_week' => 'Friday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => $room->id, 'delivery_mode' => 'online'],
        ],
    ])->assertRedirect(route('administrators.classes.show', $class))->assertSessionHasNoErrors();

    expect($class->fresh()->maximum_slots)->toBe(35)
        ->and($kept->fresh()->start_time->format('H:i'))->toBe('10:00')
        ->and($kept->fresh()->component_type)->toBe('lab')
        ->and($kept->fresh()->pattern_group_id)->toBe('pattern-123')
        ->and($kept->fresh()->status)->toBe('draft')
        ->and($removed->fresh()->trashed())->toBeTrue();
    $newMeeting = $class->schedules()->where('day_of_week', 'Friday')->firstOrFail();
    expect($newMeeting->id)->not->toBe($removed->id)->and($newMeeting->room_id)->toBeNull();
});

it('rejects invalid times and foreign schedule ids without changing records', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create();
    $other = Classes::factory()->create();
    $foreign = Schedule::factory()->create(['class_id' => $other->id]);

    $this->patch(route('administrators.classes.schedule.update', $class), [
        'schedule_revision' => app(SchedulingService::class)->revision($class),
        'schedules' => [['id' => $foreign->id, 'day_of_week' => 'Monday', 'start_time' => '10:00', 'end_time' => '09:00', 'room_id' => null, 'delivery_mode' => 'online']],
    ])->assertInvalid(['schedules.0.id', 'schedules.0.end_time']);

    expect($class->schedules()->count())->toBe(0)->and($foreign->fresh()->class_id)->toBe($other->id);
});

it('rejects malformed meetings without saving a partial schedule', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create();
    $this->patch(route('administrators.classes.schedule.update', $class), ['schedule_revision' => app(SchedulingService::class)->revision($class), 'schedules' => ['invalid']])
        ->assertInvalid(['schedules.0']);
    expect($class->schedules()->count())->toBe(0);
});

it('rejects a stale replacement without deleting another administrators meeting', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create(['classification' => 'shs']);
    $original = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00']);
    $oldRevision = app(SchedulingService::class)->revision($class);
    $newMeeting = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Friday', 'start_time' => '10:00', 'end_time' => '11:00']);

    $this->patch(route('administrators.classes.schedule.update', $class), [
        'schedule_revision' => $oldRevision,
        'schedules' => [['id' => $original->id, 'day_of_week' => 'Tuesday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => null, 'delivery_mode' => 'online']],
    ])->assertInvalid(['schedule_revision' => 'changed since you opened it']);

    expect($original->fresh()->day_of_week)->toBe('Monday')->and($newMeeting->fresh()->trashed())->toBeFalse();
});

it('rechecks overlap after the initial request validation', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create();
    $class = Classes::factory()->create(['classification' => 'shs', 'school_year' => '2026 - 2027', 'semester' => 1, 'section' => 'A']);
    $other = Classes::factory()->create(['classification' => 'shs', 'school_year' => '2026 - 2027', 'semester' => 1, 'section' => 'B']);
    $revision = app(SchedulingService::class)->revision($class);
    $payload = [['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id, 'delivery_mode' => 'face_to_face']];
    $validator = Illuminate\Support\Facades\Validator::make(['schedules' => $payload], ['schedules' => [new App\Rules\ScheduleOverlapRule($class->id)]]);
    expect($validator->passes())->toBeTrue();
    Schedule::factory()->create(['class_id' => $other->id, 'room_id' => $room->id, 'delivery_mode' => 'face_to_face', 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00']);

    try {
        app(SchedulingService::class)->replaceChecked($class, $payload, $revision, null);
        $this->fail('Expected the locked recheck to reject the conflicting meeting.');
    } catch (Illuminate\Validation\ValidationException $exception) {
        expect($exception->errors())->toHaveKey('schedules.0');
    }
    expect($class->schedules()->count())->toBe(0);
});

it('requires a schedule revision for replacements', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create();
    $this->patch(route('administrators.classes.schedule.update', $class), ['schedules' => []])->assertInvalid(['schedule_revision']);
});

it('rejects overlapping meetings in the same class', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create();
    $this->patch(route('administrators.classes.schedule.update', $class), [
        'schedule_revision' => app(SchedulingService::class)->revision($class),
        'schedules' => [
            ['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00', 'room_id' => null, 'delivery_mode' => 'online'],
            ['day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '11:00', 'room_id' => null, 'delivery_mode' => 'online'],
        ],
    ])->assertInvalid(['schedules']);
});

it('accepts meetings from other terms and validates the class term', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['name' => 'Term Room', 'is_active' => true]);
    $class = Classes::factory()->create(['classification' => 'shs', 'school_year' => '2026 - 2027', 'semester' => 2, 'section' => 'A']);
    $other = Classes::factory()->create(['classification' => 'shs', 'school_year' => '2025 - 2026', 'semester' => 2, 'section' => 'B']);
    Schedule::factory()->create(['class_id' => $other->id, 'room_id' => $room->id, 'delivery_mode' => 'face_to_face', 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00']);
    $this->patch(route('administrators.classes.schedule.update', $class), [
        'schedule_revision' => app(SchedulingService::class)->revision($class),
        'schedules' => [['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id, 'delivery_mode' => 'face_to_face']],
    ])->assertSessionHasNoErrors()->assertRedirect(route('administrators.classes.show', $class));
    expect($class->schedules()->count())->toBe(1);
});

it('preserves ids when an edit-page submission removes one meeting and adds another', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create(['classification' => 'shs', 'school_year' => '2026 - 2027', 'semester' => 1]);
    $kept = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00']);
    $removed = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Wednesday', 'start_time' => '08:00', 'end_time' => '09:00']);

    $this->patch(route('administrators.classes.update', $class), [
        'schedules_match_by_id' => true,
        'schedules' => [
            ['id' => $kept->id, 'day_of_week' => 'Monday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => null, 'delivery_mode' => 'online'],
            ['day_of_week' => 'Friday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => null, 'delivery_mode' => 'online'],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('administrators.classes.index'));

    expect($kept->fresh()->day_of_week)->toBe('Monday')
        ->and($kept->fresh()->start_time->format('H:i'))->toBe('10:00')
        ->and($removed->fresh()->trashed())->toBeTrue()
        ->and($class->schedules()->where('day_of_week', 'Friday')->firstOrFail()->id)->not->toBe($removed->id);
});

it('reports conflicting room, faculty and cohort against the affected meeting', function (string $kind) {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['name' => 'Room 201', 'is_active' => true]);
    $faculty = Faculty::factory()->create();
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $base = ['classification' => 'college', 'course_codes' => [$course->id], 'subject_ids' => [$subject->id], 'subject_id' => $subject->id, 'academic_year' => 1, 'school_year' => '2026 - 2027', 'semester' => 1];
    $class = Classes::factory()->create([...$base, 'section' => 'A', 'faculty_id' => $kind === 'faculty' ? $faculty->id : null]);
    $other = Classes::factory()->create([...$base, 'section' => $kind === 'cohort' ? 'A' : 'B', 'faculty_id' => $kind === 'faculty' ? $faculty->id : null, 'subject_code' => 'CS101']);
    Schedule::factory()->create([
        'class_id' => $other->id, 'room_id' => $kind === 'room' ? $room->id : null,
        'delivery_mode' => $kind === 'room' ? 'face_to_face' : 'online_synchronous',
        'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00',
    ]);
    $response = $this->patch(route('administrators.classes.schedule.update', $class), [
        'schedule_revision' => app(SchedulingService::class)->revision($class),
        'schedules' => [['day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '11:00', 'room_id' => $kind === 'room' ? $room->id : null, 'delivery_mode' => $kind === 'room' ? 'face_to_face' : 'online_synchronous']],
    ]);
    $response->assertInvalid(['schedules.0' => match ($kind) {
        'room' => 'Room 201', 'faculty' => 'faculty member', 'cohort' => 'student cohort',
    }]);
    expect($class->schedules()->count())->toBe(0);
})->with(['room', 'faculty', 'cohort']);

it('allows asynchronous online meetings to overlap face-to-face faculty and cohort schedules by default', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['name' => 'Room 202', 'is_active' => true]);
    $faculty = Faculty::factory()->create();
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $base = ['classification' => 'college', 'course_codes' => [$course->id], 'subject_ids' => [$subject->id], 'subject_id' => $subject->id, 'academic_year' => 1, 'school_year' => '2026 - 2027', 'semester' => 1, 'section' => 'A', 'faculty_id' => $faculty->id];
    $campus = Classes::factory()->create([...$base, 'room_id' => $room->id]);
    $asyncClass = Classes::factory()->create([...$base, 'delivery_mode' => 'online', 'subject_code' => 'CS102']);
    Schedule::factory()->create([
        'class_id' => $campus->id, 'room_id' => $room->id, 'delivery_mode' => 'face_to_face',
        'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '10:00',
    ]);

    $this->patch(route('administrators.classes.schedule.update', $asyncClass), [
        'schedule_revision' => app(SchedulingService::class)->revision($asyncClass),
        'schedules' => [['day_of_week' => 'Monday', 'start_time' => '09:00', 'end_time' => '11:00', 'room_id' => $room->id, 'delivery_mode' => 'online']],
    ])->assertRedirect(route('administrators.classes.show', $asyncClass))->assertSessionHasNoErrors();

    expect($asyncClass->schedules()->sole()->room_id)->toBeNull();
});

it('allows clearing all meetings and rejects unauthorized updates', function () {
    $class = Classes::factory()->create();
    $schedule = Schedule::factory()->create(['class_id' => $class->id]);
    $this->actingAs(User::factory()->create(['role' => UserRole::Student]))
        ->patch(route('administrators.classes.schedule.update', $class), ['schedules' => []])->assertForbidden();
    expect($schedule->fresh()->trashed())->toBeFalse();
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]))
        ->patch(route('administrators.classes.schedule.update', $class), ['schedule_revision' => app(SchedulingService::class)->revision($class), 'schedules' => []])->assertRedirect(route('administrators.classes.show', $class));
    expect($schedule->fresh()->trashed())->toBeTrue();
});

it('rolls back schedule updates if schedule change recording fails', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create();
    $meeting = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00']);
    App\Models\ClassScheduleChange::creating(fn () => throw new RuntimeException('Unable to record change'));

    try {
        $this->patch(route('administrators.classes.schedule.update', $class), [
            'schedule_revision' => app(SchedulingService::class)->revision($class),
            'schedules' => [['id' => $meeting->id, 'day_of_week' => 'Tuesday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => null, 'delivery_mode' => 'online']],
        ])->assertInternalServerError();
    } finally {
        App\Models\ClassScheduleChange::flushEventListeners();
    }

    expect($meeting->fresh()->day_of_week)->toBe('Monday')->and($meeting->fresh()->start_time->format('H:i'))->toBe('08:00');
});

it('provides existing schedule pattern metadata to the class record', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = Classes::factory()->create();
    $meeting = Schedule::factory()->create(['class_id' => $class->id, 'component_type' => 'lab', 'pattern_group_id' => 'pattern-abc', 'status' => 'draft']);
    $this->get(route('administrators.classes.show', $class))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('administrators/classes/show', false)
        ->where('raw_schedules.0.id', $meeting->id)
        ->where('raw_schedules.0.component_type', 'lab')
        ->where('raw_schedules.0.pattern_group_id', 'pattern-abc')
        ->where('raw_schedules.0.status', 'draft')
    );
});
