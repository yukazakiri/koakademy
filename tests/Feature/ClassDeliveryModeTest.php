<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Mcp\Servers\KoAkademyServer;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassScheduleChange;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\School;
use App\Models\StrandSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ClassScheduleChangedNotification;
use App\Services\ClassScheduleChangeNotificationService;
use App\Services\CourseScheduleExportService;
use App\Services\Scheduling\ScheduleConflictService;
use App\Services\Scheduling\SchedulingService;
use App\Services\Scheduling\TermOfferingGeneratorService;
use App\Services\StudentScheduleService;
use App\Services\TenantContext;
use App\Services\TimetableConflictService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Laravel\Ai\Tools\Request as AiRequest;
use Spatie\Permission\Models\Permission;

function deliveryModeClass(array $attributes = []): Classes
{
    return Classes::factory()->create([
        'classification' => 'college', 'school_year' => '2031 - 2032', 'semester' => 2,
        'section' => 'A', 'academic_year' => 1, 'faculty_id' => null, 'room_id' => null,
        ...$attributes,
    ]);
}

function deliveryModeBlock(Classes $class, array $attributes = []): Schedule
{
    return Schedule::factory()->create([
        'class_id' => $class->id, 'day_of_week' => 'Monday',
        'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => null,
        ...$attributes,
    ]);
}

function deliveryModePayload(array $attributes = []): array
{
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);

    return [
        'classification' => 'college', 'course_codes' => [$course->id],
        'subject_ids' => [$subject->id], 'subject_code' => $subject->code, 'academic_year' => 1,
        'school_year' => '2031 - 2032', 'semester' => '2', 'section' => 'A',
        'maximum_slots' => 40, 'delivery_mode' => 'online',
        ...$attributes,
    ];
}

it('creates an online planner offering despite a stale occupied room', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create();
    $other = deliveryModeClass(['section' => 'B', 'room_id' => $room->id]);
    deliveryModeBlock($other, ['room_id' => $room->id]);

    $response = $this->postJson(route('administrators.scheduling-analytics.classes.store'), deliveryModePayload([
        'room_id' => $room->id,
        'schedules' => [[
            'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00',
            'room_id' => $room->id, 'status' => 'draft', 'component_type' => 'lab',
        ]],
    ]));

    $response->assertOk()->assertJsonPath('class.delivery_mode', 'online')
        ->assertJsonPath('class.room_id', null)->assertJsonPath('class.schedules.0.room_id', null)
        ->assertJsonPath('class.schedules.0.component_type', 'lab')->assertJsonPath('conflicts', []);
    $this->assertDatabaseHas('classes', ['id' => $response->json('class.id'), 'delivery_mode' => 'online', 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['class_id' => $response->json('class.id'), 'room_id' => null, 'status' => 'draft']);
});

it('rejects faculty and cohort clashes when creating online classes with stale rooms', function (string $conflict): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $faculty = Faculty::factory()->create();
    $room = Room::factory()->create();
    $payload = deliveryModePayload([
        'faculty_id' => $conflict === 'faculty' ? $faculty->id : null,
        'room_id' => $room->id,
        'schedules' => [[
            'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id,
        ]],
    ]);
    $other = deliveryModeClass([
        'course_codes' => $payload['course_codes'], 'faculty_id' => $faculty->id,
        'section' => $conflict === 'faculty' ? 'B' : 'A', 'room_id' => $room->id,
    ]);
    deliveryModeBlock($other, ['room_id' => $room->id]);

    $this->post(route('administrators.classes.store'), $payload)
        ->assertInvalid(['schedules' => $conflict === 'faculty' ? 'faculty member is already scheduled' : 'student cohort is already scheduled']);

    $this->assertDatabaseCount('classes', 1);
    $this->assertDatabaseCount('schedule', 1);
})->with(['faculty', 'cohort']);

it('changes to online without requiring or replacing schedules and clears archived rooms', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create();
    $class = deliveryModeClass(['room_id' => $room->id]);
    $active = deliveryModeBlock($class, ['room_id' => $room->id]);
    $archived = deliveryModeBlock($class, ['room_id' => $room->id, 'day_of_week' => 'Wednesday']);
    $archived->delete();

    $this->patch(route('administrators.classes.update', $class), ['delivery_mode' => 'online'])
        ->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'delivery_mode' => 'online', 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $active->id, 'room_id' => null, 'deleted_at' => null]);
    $this->assertSoftDeleted($archived);
    expect($class->schedules()->withTrashed()->count())->toBe(2);
    expect($archived->fresh()->room_id)->toBeNull();
});

it('preserves online schedules during unrelated partial updates', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class);

    $this->patch(route('administrators.classes.update', $class), ['maximum_slots' => 48])
        ->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'maximum_slots' => 48, 'delivery_mode' => 'online']);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'deleted_at' => null]);
    expect($class->schedules()->withTrashed()->count())->toBe(1);
});

it('keeps rooms unassigned when switching back to face to face', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Room::factory()->create(['is_active' => true]);
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class);

    $this->patch(route('administrators.classes.update', $class), ['delivery_mode' => 'face_to_face'])
        ->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'delivery_mode' => 'face_to_face', 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'room_id' => null]);
});

it('strips rooms from online partial schedule updates when the mode is omitted', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class);

    $this->patch(route('administrators.classes.update', $class), [
        'room_id' => 999999,
        'schedules' => [[
            'id' => $schedule->id, 'day_of_week' => 'Tuesday',
            'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => 999999,
        ]],
    ])->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'day_of_week' => 'Tuesday', 'room_id' => null]);
});

it('retains faculty and cohort validation on online partial schedule updates', function (string $conflict): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $course = Course::factory()->create();
    $subjects = Subject::factory()->count(2)->create(['course_id' => $course->id, 'academic_year' => 1]);
    $faculty = Faculty::factory()->create();
    $other = deliveryModeClass([
        'course_codes' => [$course->id], 'faculty_id' => $faculty->id,
        'subject_id' => $subjects[0]->id, 'subject_ids' => [$subjects[0]->id],
        'section' => $conflict === 'faculty' ? 'B' : 'A',
    ]);
    deliveryModeBlock($other);
    $class = deliveryModeClass([
        'delivery_mode' => 'online', 'course_codes' => [$course->id],
        'subject_id' => $subjects[1]->id, 'subject_ids' => [$subjects[1]->id],
        'faculty_id' => $conflict === 'faculty' ? $faculty->id : null,
    ]);
    $schedule = deliveryModeBlock($class, ['day_of_week' => 'Tuesday']);

    $this->patch(route('administrators.classes.update', $class), [
        'maximum_slots' => 75,
        'schedules' => [[
            'id' => $schedule->id, 'day_of_week' => 'Monday',
            'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => 999999,
        ]],
    ])->assertInvalid(['schedules' => $conflict === 'faculty' ? 'faculty member is already scheduled' : 'student cohort is already scheduled']);

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'maximum_slots' => $class->maximum_slots]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'day_of_week' => 'Tuesday']);
})->with(['faculty', 'cohort']);

it('validates retained schedules when assigning faculty in a partial update', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $faculty = Faculty::factory()->create();
    $other = deliveryModeClass(['faculty_id' => $faculty->id, 'section' => 'B']);
    deliveryModeBlock($other);
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class);

    $this->patch(route('administrators.classes.update', $class), ['faculty_id' => $faculty->id])
        ->assertInvalid(['schedules' => 'faculty member is already scheduled']);

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'faculty_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'deleted_at' => null]);
});

it('leaves face to face sessions unassigned when assign later is selected despite a primary room', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['is_active' => true]);
    $class = deliveryModeClass(['room_id' => $room->id]);

    $this->postJson(route('administrators.scheduling-analytics.classes.schedules.store', $class), [
        'days' => ['Monday', 'Wednesday'], 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => null,
    ])->assertOk()->assertJsonPath('class.delivery_mode', 'face_to_face')
        ->assertJsonPath('class.room_id', $room->id)->assertJsonPath('class.schedules.0.room_id', null)
        ->assertJsonPath('class.schedules.1.room_id', null);

    expect($class->schedules()->count())->toBe(2);
    expect($class->schedules()->whereNotNull('room_id')->exists())->toBeFalse();
});

it('uses the primary room for a planner pattern only when the room field is omitted', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create();
    $class = deliveryModeClass(['room_id' => $room->id]);

    $this->postJson(route('administrators.scheduling-analytics.classes.schedules.store', $class), [
        'days' => ['Tuesday'], 'start_time' => '08:00', 'end_time' => '11:00',
    ])->assertOk()->assertJsonPath('class.schedules.0.room_id', $room->id);

    $this->assertDatabaseHas('schedule', ['class_id' => $class->id, 'day_of_week' => 'Tuesday', 'room_id' => $room->id]);
});

it('normalizes online planner patterns before validation without hiding faculty or cohort conflicts', function (string $conflict): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $course = Course::factory()->create();
    $faculty = Faculty::factory()->create();
    $other = deliveryModeClass([
        'course_codes' => [$course->id], 'faculty_id' => $faculty->id,
        'section' => $conflict === 'faculty' ? 'B' : 'A',
    ]);
    deliveryModeBlock($other);
    $class = deliveryModeClass([
        'delivery_mode' => 'online', 'course_codes' => [$course->id],
        'faculty_id' => $conflict === 'faculty' ? $faculty->id : null,
    ]);

    $this->postJson(route('administrators.scheduling-analytics.classes.schedules.store', $class), [
        'days' => ['Monday', 'Wednesday'], 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => 999999,
    ])->assertOk()->assertJsonPath('class.delivery_mode', 'online')
        ->assertJsonPath('class.schedules.0.room_id', null)->assertJsonPath('class.schedules.1.room_id', null)
        ->assertJsonPath('conflicts.0.conflict_type', $conflict === 'faculty' ? 'faculty' : 'section');

    expect($class->schedules()->whereNotNull('room_id')->exists())->toBeFalse();
})->with(['faculty', 'cohort']);

it('clears rooms from linked online pattern blocks during a planner update', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $first = deliveryModeBlock($class, ['pattern_group_id' => 'shared-pattern']);
    $second = deliveryModeBlock($class, ['pattern_group_id' => 'shared-pattern', 'day_of_week' => 'Wednesday']);
    $unrelated = deliveryModeBlock(deliveryModeClass(['section' => 'B']), ['pattern_group_id' => 'shared-pattern']);

    $this->patchJson(route('administrators.scheduling-analytics.schedules.update', $first), [
        'day_of_week' => 'Monday', 'start_time' => '10:00', 'end_time' => '11:00',
        'room_id' => 999999, 'apply_to_pattern_group' => true,
    ])->assertOk()->assertJsonPath('schedule.room_id', null)->assertJsonPath('schedule.room', null);

    foreach ([$first, $second] as $block) {
        $this->assertDatabaseHas('schedule', ['id' => $block->id, 'room_id' => null, 'start_time' => '10:00:00']);
    }
    $this->assertDatabaseHas('schedule', ['id' => $unrelated->id, 'start_time' => '08:00']);
});

it('normalizes direct linked pattern updates with a stale loaded parent', function (): void {
    $room = Room::factory()->create();
    $class = deliveryModeClass(['room_id' => $room->id]);
    $first = deliveryModeBlock($class, ['room_id' => $room->id, 'pattern_group_id' => 'pattern']);
    $second = deliveryModeBlock($class, ['room_id' => $room->id, 'pattern_group_id' => 'pattern', 'day_of_week' => 'Wednesday']);
    $first->load('class');
    $class->update(['delivery_mode' => 'online']);

    app(SchedulingService::class)->updateScheduleBlock($first, ['room_id' => $room->id], null, true);

    $this->assertDatabaseHas('schedule', ['id' => $first->id, 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $second->id, 'room_id' => null]);
});

it('allows faculty to retain roomless sessions without reassigning rooms', function (string $mode): void {
    $faculty = Faculty::factory()->create();
    $this->actingAs(User::factory()->create([
        'role' => UserRole::Instructor, 'email' => $faculty->email, 'faculty_id_number' => 'F-ONLINE',
    ]));
    $class = deliveryModeClass(['delivery_mode' => $mode, 'faculty_id' => $faculty->id]);
    $schedule = deliveryModeBlock($class);

    $this->put(route('faculty.classes.schedules.update', $class), [
        'schedules' => [[
            'id' => $schedule->id, 'day_of_week' => 'Wednesday',
            'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => $mode === 'online' ? 999999 : null,
        ]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'day_of_week' => 'Wednesday', 'room_id' => null]);
})->with(['online', 'face_to_face']);

it('strips rooms on model saves even when the loaded parent and room are stale', function (): void {
    $room = Room::factory()->create();
    $class = deliveryModeClass(['room_id' => $room->id]);
    $first = deliveryModeBlock($class, ['room_id' => $room->id]);
    $first->load(['class', 'room']);
    $class->load(['Room', 'schedules.room']);
    $class->update(['delivery_mode' => 'online']);

    $first->update(['room_id' => $room->id]);

    expect($first->room)->toBeNull();
    expect($class->Room)->toBeNull();
    expect($class->schedules->pluck('room_id')->all())->toBe([null]);
    $this->assertDatabaseHas('schedule', ['id' => $first->id, 'room_id' => null]);
});

it('copies online schedules without rooms and preserves pattern metadata', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    deliveryModeBlock($class, ['component_type' => 'lab', 'status' => 'draft', 'pattern_group_id' => 'source-pattern']);
    deliveryModeBlock($class, ['component_type' => 'lab', 'status' => 'draft', 'pattern_group_id' => 'source-pattern', 'day_of_week' => 'Wednesday']);

    $this->post(route('administrators.classes.copy', $class), ['section' => 'B', 'copy_schedules' => true])
        ->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    $copy = Classes::query()->whereKeyNot($class->id)->sole();
    $this->assertDatabaseHas('classes', ['id' => $copy->id, 'delivery_mode' => 'online', 'room_id' => null, 'section' => 'B']);
    expect($copy->schedules()->count())->toBe(2);
    expect($copy->schedules()->whereNotNull('room_id')->exists())->toBeFalse();
    expect($copy->schedules->pluck('pattern_group_id')->unique()->all())->toHaveCount(1);
    expect($copy->schedules->first()->pattern_group_id)->not->toBe('source-pattern');
    $this->assertDatabaseHas('schedule', ['class_id' => $copy->id, 'component_type' => 'lab', 'status' => 'draft']);
});

it('clears source rooms when schedules are copied to an online target', function (): void {
    $room = Room::factory()->create();
    $source = deliveryModeClass(['room_id' => $room->id]);
    deliveryModeBlock($source, ['room_id' => $room->id]);
    $target = deliveryModeClass(['delivery_mode' => 'online', 'section' => 'B']);

    app(SchedulingService::class)->copyClassSchedules($source, $target);

    $this->assertDatabaseHas('schedule', ['class_id' => $source->id, 'room_id' => $room->id]);
    $this->assertDatabaseHas('schedule', ['class_id' => $target->id, 'room_id' => null]);
});

it('preserves delivery mode when cloning offerings into another term', function (): void {
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    deliveryModeBlock($class);

    $result = app(TermOfferingGeneratorService::class)->cloneTermOfferings('2031 - 2032', 2, '2032 - 2033', 1);

    expect($result['created'])->toHaveCount(1);
    $copy = $result['created']->sole();
    $this->assertDatabaseHas('classes', ['id' => $copy->id, 'delivery_mode' => 'online', 'school_year' => '2032 - 2033', 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['class_id' => $copy->id, 'room_id' => null]);
});

it('generates college offerings with the selected delivery mode', function (string $mode): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $course = Course::factory()->create();
    Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1, 'semester' => 2]);

    $this->postJson(route('administrators.scheduling-analytics.offerings.generate'), [
        'classification' => 'college', 'delivery_mode' => $mode, 'course_id' => $course->id,
        'academic_year' => 1, 'school_year' => '2031 - 2032', 'semester' => '2',
        'sections' => ['A', 'B'], 'room_id' => $mode === 'online' ? 999999 : null,
    ])->assertOk()->assertJsonPath('created_count', 2)
        ->assertJsonPath('classes.0.delivery_mode', $mode)->assertJsonPath('classes.0.room_id', null);

    expect(Classes::query()->where('delivery_mode', $mode)->count())->toBe(2);
    expect(Classes::query()->whereNotNull('room_id')->exists())->toBeFalse();
})->with(['online', 'face_to_face']);

it('generates online SHS offerings without rooms', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $subject = StrandSubject::factory()->create(['grade_year' => 11, 'semester' => 2]);

    $this->postJson(route('administrators.scheduling-analytics.offerings.generate'), [
        'classification' => 'shs', 'delivery_mode' => 'online', 'shs_strand_id' => $subject->strand_id,
        'grade_level' => 'Grade 11', 'school_year' => '2031 - 2032', 'semester' => '2',
        'sections' => ['A'], 'room_id' => 999999,
    ])->assertOk()->assertJsonPath('created_count', 1)->assertJsonPath('classes.0.delivery_mode', 'online');

    $this->assertDatabaseHas('classes', ['subject_code' => $subject->code, 'delivery_mode' => 'online', 'room_id' => null]);
});

it('does not invent room conflicts between roomless sessions in either conflict service', function (string $mode): void {
    $a = deliveryModeClass(['delivery_mode' => $mode]);
    $b = deliveryModeClass(['delivery_mode' => $mode, 'section' => 'B']);
    deliveryModeBlock($a);
    deliveryModeBlock($b);

    expect(app(ScheduleConflictService::class)->detectConflictsForClasses(Classes::with(['Faculty', 'Schedule.room'])->get()))->toBe([]);
    expect(app(TimetableConflictService::class)->detectTimeRoomConflicts(Schedule::with('class')->get()))->toBe([]);
})->with(['online', 'face_to_face']);

it('serializes delivery mode and null rooms in list form detail and planner responses', function (string $mode): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    GeneralSetting::factory()->create([
        'school_starting_date' => '2031-08-01', 'school_ending_date' => '2032-05-31', 'semester' => 2,
    ]);
    $class = deliveryModeClass(['delivery_mode' => $mode]);
    deliveryModeBlock($class);

    $this->get(route('administrators.classes.index', ['selected' => $class->id]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('classes.data.0.delivery_mode', $mode)->where('selected_class.delivery_mode', $mode)
        ->where('selected_class.room', null)->where('selected_class.schedules.0.room_id', null));

    $this->get(route('administrators.classes.edit', $class))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('defaults.delivery_mode', $mode)->where('defaults.room_id', null)
        ->where('defaults.schedules.0.room_id', null));

    $this->get(route('administrators.classes.show', $class))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('class.delivery_mode', $mode)->where('raw_schedules.0.room_id', null));

    $this->get(route('administrators.scheduling-analytics.index', ['school_year' => '2031 - 2032', 'semester' => 2]))
        ->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->where('schedule_data.0.delivery_mode', $mode)->where('schedule_data.0.room_id', null)
        ->where('schedule_data.0.schedules.0.room_id', null));
})->with(['online', 'face_to_face']);

it('rejects invalid delivery modes before creating an offering', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $this->postJson(route('administrators.scheduling-analytics.classes.store'), deliveryModePayload(['delivery_mode' => 'hybrid']))
        ->assertUnprocessable()->assertJsonValidationErrors('delivery_mode');

    $this->assertDatabaseCount('classes', 0);
});

it('labels online locations in student schedules while retaining unassigned face to face locations', function (string $mode, string $classRoom, string $sessionRoom): void {
    $student = Student::factory()->create();
    $class = deliveryModeClass(['delivery_mode' => $mode]);
    deliveryModeBlock($class);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $student->id, 'status' => true]);

    $schedule = app(StudentScheduleService::class)->build($student, '2031 - 2032', 2);

    expect($schedule['classes'])->toHaveCount(1);
    expect($schedule['classes'][0])->toMatchArray(['delivery_mode' => $mode, 'room' => $classRoom, 'room_id' => null]);
    expect($schedule['classes'][0]['schedules'][0])->toMatchArray(['room' => $sessionRoom, 'room_id' => null]);
})->with([
    'online' => ['online', 'Online', 'Online'],
    'unassigned face to face' => ['face_to_face', 'TBA', 'Room TBA'],
]);

it('labels online locations and delivery in program exports', function (string $mode, string $room, string $delivery): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2031-08-01', 'school_ending_date' => '2032-05-31', 'semester' => 2,
    ]);
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $class = deliveryModeClass([
        'delivery_mode' => $mode, 'course_codes' => [$course->id],
        'subject_id' => $subject->id, 'subject_ids' => [$subject->id], 'subject_code' => $subject->code,
    ]);
    deliveryModeBlock($class);

    $export = app(CourseScheduleExportService::class)->build($course);

    expect($export['year_groups'])->toHaveCount(1);
    expect($export['year_groups'][0]['section_groups'][0]['rows'][0])->toMatchArray([
        'delivery_mode' => $mode, 'room' => $room, 'face_to_face' => $delivery,
    ]);
})->with([
    'online' => ['online', 'Online', 'Online'],
    'unassigned face to face' => ['face_to_face', '—', 'MONDAY'],
]);

it('prevents AI room assignment from bypassing the online invariant', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->givePermissionTo(Permission::findOrCreate('Update:Classes', 'web'));
    $this->actingAs($admin);
    $room = Room::factory()->create();
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class);

    $result = json_decode((string) app(App\Ai\Tools\ManageClassScheduleTool::class)->handle(new AiRequest([
        'action' => 'assign_room', 'class_id' => $class->id, 'room_id' => $room->id,
    ])), true);

    expect($result)->toMatchArray(['error' => true, 'message' => 'Online classes cannot be assigned a room.']);
    $this->assertDatabaseHas('classes', ['id' => $class->id, 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'room_id' => null]);
});

it('prevents MCP room assignment from bypassing the online invariant', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $room = Room::factory()->create();
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class);

    KoAkademyServer::actingAs($admin)->tool(App\Mcp\Tools\ManageClassScheduleTool::class, [
        'action' => 'assign_room', 'class_id' => $class->id, 'room_id' => $room->id,
    ])->assertOk()->assertStructuredContent(fn ($json) => $json
        ->where('error', true)->where('message', 'Online classes cannot be assigned a room.'));

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'room_id' => null]);
});

it('normalizes stale online rooms before AI rescheduling conflict checks', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->givePermissionTo(Permission::findOrCreate('Update:Classes', 'web'));
    $this->actingAs($admin);
    $room = Room::factory()->create();
    deliveryModeBlock(deliveryModeClass(['section' => 'B']), ['room_id' => $room->id]);
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class, ['day_of_week' => 'Tuesday']);

    $result = json_decode((string) app(App\Ai\Tools\ManageClassScheduleTool::class)->handle(new AiRequest([
        'action' => 'reschedule', 'class_id' => $class->id, 'day_of_week' => 'Monday',
        'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id,
    ])), true);

    expect($result['success'] ?? false)->toBeTrue();
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'day_of_week' => 'Monday', 'room_id' => null]);
});

it('normalizes stale online rooms before MCP rescheduling conflict checks', function (): void {
    $school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($school);
    $admin = User::factory()->create(['role' => UserRole::Admin, 'school_id' => $school->id]);
    $room = Room::factory()->create();
    deliveryModeBlock(deliveryModeClass(['section' => 'B']), ['room_id' => $room->id]);
    $class = deliveryModeClass(['delivery_mode' => 'online']);
    $schedule = deliveryModeBlock($class, ['day_of_week' => 'Tuesday']);

    KoAkademyServer::actingAs($admin)->tool(App\Mcp\Tools\ManageClassScheduleTool::class, [
        'action' => 'reschedule', 'class_id' => $class->id, 'day_of_week' => 'Monday',
        'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id,
    ])->assertOk()->assertStructuredContent(fn ($json) => $json
        ->where('success', true)->where('schedule_id', $schedule->id)->etc());

    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'day_of_week' => 'Monday', 'room_id' => null]);
});

it('review rejects online creation against the normalized curriculum cohort', function (string $routeName): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $payload = deliveryModePayload([
        'academic_year' => 2,
        'schedules' => [['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => null]],
    ]);
    $other = deliveryModeClass([
        'course_codes' => $payload['course_codes'], 'subject_id' => $payload['subject_ids'][0],
        'subject_ids' => $payload['subject_ids'], 'academic_year' => 1,
    ]);
    deliveryModeBlock($other);

    $this->postJson(route($routeName), $payload)
        ->assertUnprocessable()->assertInvalid(['schedules' => 'student cohort is already scheduled']);

    $this->assertDatabaseCount('classes', 1);
    $this->assertDatabaseCount('schedule', 1);
})->with(['administrators.classes.store', 'administrators.scheduling-analytics.classes.store']);

it('review checks the normalized curriculum cohort when editing with supplied or retained sessions', function (bool $supplySchedules): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $course = Course::factory()->create();
    $yearOne = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $yearTwo = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 2]);
    $other = deliveryModeClass(['course_codes' => [$course->id], 'subject_id' => $yearOne->id, 'subject_ids' => [$yearOne->id]]);
    deliveryModeBlock($other);
    $class = deliveryModeClass([
        'delivery_mode' => 'online', 'course_codes' => [$course->id], 'academic_year' => 2,
        'subject_id' => $yearTwo->id, 'subject_ids' => [$yearTwo->id],
    ]);
    $initialDay = $supplySchedules ? 'Tuesday' : 'Monday';
    $schedule = deliveryModeBlock($class, ['day_of_week' => $initialDay]);
    $payload = ['classification' => 'college', 'course_codes' => [$course->id], 'subject_ids' => [$yearOne->id], 'academic_year' => 2];
    if ($supplySchedules) {
        $payload['schedules'] = [[
            'id' => $schedule->id, 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => null,
        ]];
    }

    $this->patchJson(route('administrators.classes.update', $class), $payload)
        ->assertUnprocessable()->assertInvalid(['schedules' => 'student cohort is already scheduled']);

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'academic_year' => 2, 'subject_id' => $yearTwo->id]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'day_of_week' => $initialDay]);
})->with(['supplied sessions' => true, 'retained sessions' => false]);

it('review resolves stored subject placement before a partial delivery update', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $attributes = ['course_codes' => [$course->id], 'subject_id' => $subject->id, 'subject_ids' => [$subject->id]];
    deliveryModeBlock(deliveryModeClass($attributes));
    $class = deliveryModeClass([...$attributes, 'academic_year' => 2]);
    $schedule = deliveryModeBlock($class);

    $this->patchJson(route('administrators.classes.update', $class), ['delivery_mode' => 'online'])
        ->assertUnprocessable()->assertInvalid(['schedules' => 'student cohort is already scheduled']);

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'delivery_mode' => 'face_to_face', 'academic_year' => 2]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'deleted_at' => null]);
});

it('review preserves an explicitly unassigned student session despite the primary room', function (): void {
    $room = Room::factory()->create(['name' => 'Primary Room R']);
    $class = deliveryModeClass(['room_id' => $room->id]);
    deliveryModeBlock($class);
    $student = Student::factory()->create();
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $student->id, 'status' => true]);

    $schedule = app(StudentScheduleService::class)->build($student, '2031 - 2032', 2);

    expect($schedule['classes'][0])->toMatchArray(['room_id' => $room->id, 'room' => 'Primary Room R']);
    expect($schedule['classes'][0]['schedules'][0])->toMatchArray(['room_id' => null, 'room' => 'Room TBA']);
});

it('review notifies once when converting assigned or roomless sessions online with supplied or omitted schedules', function (bool $assignedRoom, bool $supplySchedules): void {
    Notification::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);
    $room = $assignedRoom ? Room::factory()->create(['name' => 'Room R']) : null;
    $class = deliveryModeClass(['room_id' => $room?->id]);
    $schedule = deliveryModeBlock($class, ['room_id' => $room?->id]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email]);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $student->id, 'status' => true]);
    $payload = ['delivery_mode' => 'online'];
    if ($supplySchedules) {
        $payload['schedules'] = [[
            'id' => $schedule->id, 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room?->id,
        ]];
    }

    $this->patch(route('administrators.classes.update', $class), $payload)
        ->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    $change = ClassScheduleChange::query()->where('class_id', $class->id)->sole();
    expect($change->changed_by_user_id)->toBe($admin->id);
    expect($change->old_schedule[0])->toMatchArray(['delivery_mode' => 'face_to_face', 'room_id' => $room?->id]);
    expect($change->new_schedule[0])->toMatchArray(['delivery_mode' => 'online', 'room_id' => null, 'room_name' => 'Online']);
    Notification::assertSentTo($user, ClassScheduleChangedNotification::class, fn ($notification): bool => $notification->toArray($user)['new_schedule'] === ['Monday, 8:00 AM-9:00 AM (Online)']);
    $this->assertDatabaseHas('classes', ['id' => $class->id, 'delivery_mode' => 'online', 'room_id' => null]);
    $this->assertDatabaseHas('schedule', ['id' => $schedule->id, 'room_id' => null, 'deleted_at' => null]);

    $this->patch(route('administrators.classes.update', $class), $payload)
        ->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    expect($class->scheduleChanges()->count())->toBe(1);
    Notification::assertSentToTimes($user, ClassScheduleChangedNotification::class, 1);
    Notification::assertSentOnDemandTimes(ClassScheduleChangedNotification::class, 1);
})->with([
    'assigned room and supplied schedules' => [true, true],
    'assigned room and omitted schedules' => [true, false],
    'roomless and supplied schedules' => [false, true],
    'roomless and omitted schedules' => [false, false],
]);

it('review sends one final notification for a combined delivery and time change', function (): void {
    Notification::fake();
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['name' => 'Room R']);
    $class = deliveryModeClass(['room_id' => $room->id]);
    $schedule = deliveryModeBlock($class, ['room_id' => $room->id]);
    $user = User::factory()->create(['role' => UserRole::Student]);
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => $user->email]);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $student->id, 'status' => true]);

    $this->patch(route('administrators.classes.update', $class), [
        'delivery_mode' => 'online',
        'schedules' => [['id' => $schedule->id, 'day_of_week' => 'Tuesday', 'start_time' => '10:00', 'end_time' => '11:00', 'room_id' => $room->id]],
    ])->assertRedirect(route('administrators.classes.index'))->assertSessionHasNoErrors();

    expect($class->scheduleChanges()->count())->toBe(1);
    Notification::assertSentToTimes($user, ClassScheduleChangedNotification::class, 1);
    Notification::assertSentOnDemandTimes(ClassScheduleChangedNotification::class, 1);
    Notification::assertSentTo($user, ClassScheduleChangedNotification::class, fn ($notification): bool => $notification->toArray($user)['old_schedule'] === ['Monday, 8:00 AM-9:00 AM at Room R']
        && $notification->toArray($user)['new_schedule'] === ['Tuesday, 10:00 AM-11:00 AM (Online)']);
});

it('review treats old snapshots without a delivery mode as face to face', function (): void {
    Notification::fake();
    $class = deliveryModeClass();
    deliveryModeBlock($class);
    $service = app(ClassScheduleChangeNotificationService::class);
    $legacySnapshot = array_map(fn (array $entry): array => Arr::except($entry, 'delivery_mode'), $service->snapshot($class));

    expect($service->notifyIfChanged($class, $legacySnapshot))->toBe(0);
    expect($class->scheduleChanges()->count())->toBe(0);

    $class->update(['delivery_mode' => 'online']);
    $service->notifyIfChanged($class, $legacySnapshot);

    $change = $class->scheduleChanges()->sole();
    expect($change->old_schedule[0])->not->toHaveKey('delivery_mode');
    expect($change->new_schedule[0])->toMatchArray(['delivery_mode' => 'online', 'room_name' => 'Online']);
    Notification::assertNothingSent();
});

it('updates class schedules through the API without resubmitting unchanged curriculum', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->givePermissionTo(Permission::findOrCreate('Update:Classes', 'web'));
    $this->actingAs($admin, 'sanctum');
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['course_id' => $course->id, 'academic_year' => 1]);
    $room = Room::factory()->create();
    $class = deliveryModeClass([
        'course_codes' => [$course->id], 'subject_id' => $subject->id,
        'subject_ids' => [$subject->id], 'room_id' => $room->id,
    ]);
    deliveryModeBlock($class, ['room_id' => $room->id]);

    $this->putJson(route('api.classes.update', ['id' => $class->id]), [
        'classification' => 'college', 'maximum_slots' => 48,
        'schedules' => [[
            'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => $room->id,
        ]],
    ])->assertOk();

    $this->assertDatabaseHas('classes', ['id' => $class->id, 'maximum_slots' => 48, 'subject_id' => $subject->id, 'academic_year' => 1]);
    expect($class->fresh()->course_codes)->toBe([$course->id]);
    expect($class->schedules()->count())->toBe(1);
    $this->assertDatabaseHas('schedule', ['class_id' => $class->id, 'day_of_week' => 'Monday', 'room_id' => $room->id, 'deleted_at' => null]);
});
