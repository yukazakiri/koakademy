<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassScheduleChange;
use App\Models\ClassScheduleChangeStudentNotification;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ClassScheduleChangedNotification;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

beforeEach(function (): void {
    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function (Blueprint $table): void {
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

    if (! Schema::hasTable('document_locations')) {
        Schema::create('document_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('picture_1x1')->nullable();
            $table->timestamps();
        });
    }
});

/**
 * @param  array<string, mixed>  $overrides
 */
function crudSurfaceStudent(Course $course, array $overrides = []): Student
{
    return Student::factory()->create(array_merge([
        'course_id' => $course->id,
        'document_location_id' => null,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function crudSurfaceRoom(string $name, int $capacity = 40, array $overrides = []): Room
{
    return Room::query()->create(array_merge([
        'name' => $name,
        'class_code' => 'LEC',
        'capacity' => $capacity,
        'type' => 'lecture',
        'is_active' => true,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function crudSurfaceEnrollment(Classes $class, Student $student, array $overrides = []): ClassEnrollment
{
    return ClassEnrollment::factory()->create(array_merge([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
        'enrollment_status' => ClassEnrollment::STATUS_ENROLLED,
        'prelim_grade' => null,
        'midterm_grade' => null,
        'finals_grade' => null,
        'total_average' => null,
        'grade_symbol' => null,
        'is_grades_verified' => false,
        'is_grades_finalized' => false,
        'verified_by' => null,
        'verified_at' => null,
        'verification_notes' => null,
        'remarks' => null,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function crudSurfaceSubject(Course $course, array $overrides = []): Subject
{
    return Subject::factory()->create(array_merge([
        'course_id' => $course->id,
        'academic_year' => 1,
        'lecture' => 3,
        'laboratory' => 0,
        'units' => 3,
    ], $overrides));
}

// =========================================================================
// 1. POST /administrators/classes/preflight
// =========================================================================

it('detects blocking room conflict on preflight and returns ranked free room suggestions and busy overlay', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'PRE-ROOM-101', 'title' => 'Database Systems']);
    $otherSubject = crudSurfaceSubject($course, ['code' => 'PRE-ROOM-102', 'title' => 'Discrete Math']);

    $occupiedRoom = crudSurfaceRoom('Room-PF-Busy', 40);
    $freeCloseFitRoom = crudSurfaceRoom('Room-PF-BestFit', 35);
    $freeLargeRoom = crudSurfaceRoom('Room-PF-Large', 80);

    $existingClass = Classes::factory()->create([
        'subject_id' => $otherSubject->id,
        'subject_code' => $otherSubject->code,
        'section' => 'A',
        'room_id' => $occupiedRoom->id,
        'course_codes' => [$course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    Schedule::query()->create([
        'class_id' => $existingClass->id,
        'room_id' => $occupiedRoom->id,
        'day_of_week' => 'Monday',
        'start_time' => '09:00:00',
        'end_time' => '10:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'component_type' => 'lecture',
        'status' => 'published',
    ]);

    $response = $this->postJson(route('administrators.classes.workspace.preflight'), [
        'classification' => 'college',
        'school_year' => $schoolYear,
        'semester' => $semester,
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'academic_year' => 1,
        'room_id' => $occupiedRoom->id,
        'maximum_slots' => 30,
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'schedules' => [
            [
                'day_of_week' => 'Monday',
                'start_time' => '09:30',
                'end_time' => '11:00',
                'room_id' => $occupiedRoom->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('can_publish', false);

    $conflicts = collect($response->json('conflicts'));
    expect($conflicts->contains(fn (array $c): bool => $c['kind'] === 'room' && $c['severity'] === 'blocking'))->toBeTrue();

    $roomBusy = collect($response->json('busy_overlay.room_busy'));
    expect($roomBusy)->not->toBeEmpty();
    expect($roomBusy->first()['class_id'])->toBe($existingClass->id);

    $roomSuggestions = collect($response->json('suggestions'))->where('kind', 'room')->values();
    expect($roomSuggestions)->not->toBeEmpty();
    expect($roomSuggestions->pluck('room_id')->all())->toContain($freeCloseFitRoom->id);
    expect($roomSuggestions->pluck('room_id')->all())->not->toContain($occupiedRoom->id);
    // Closer-capacity room (35 seats for 30 slots) ranks ahead of oversized room (80 seats)
    $bestFitIndex = $roomSuggestions->search(fn (array $s): bool => $s['room_id'] === $freeCloseFitRoom->id);
    $largeIndex = $roomSuggestions->search(fn (array $s): bool => $s['room_id'] === $freeLargeRoom->id);
    if ($largeIndex !== false) {
        expect($bestFitIndex)->toBeLessThan($largeIndex);
    }
});

it('detects blocking faculty conflict on preflight and sets can_publish to false', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $faculty = Faculty::factory()->create();
    $roomA = crudSurfaceRoom('Room-FAC-A', 40);
    $roomB = crudSurfaceRoom('Room-FAC-B', 40);
    $subjectA = crudSurfaceSubject($course, ['code' => 'FAC-101']);
    $subjectB = crudSurfaceSubject($course, ['code' => 'FAC-102']);

    $existingClass = Classes::factory()->create([
        'subject_id' => $subjectA->id,
        'subject_code' => $subjectA->code,
        'section' => 'A',
        'faculty_id' => $faculty->id,
        'room_id' => $roomA->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    Schedule::query()->create([
        'class_id' => $existingClass->id,
        'room_id' => $roomA->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '13:00:00',
        'end_time' => '14:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'component_type' => 'lecture',
        'status' => 'published',
    ]);

    $response = $this->postJson(route('administrators.classes.workspace.preflight'), [
        'classification' => 'college',
        'faculty_id' => $faculty->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'course_codes' => [$course->id],
        'subject_ids' => [$subjectB->id],
        'subject_code' => $subjectB->code,
        'academic_year' => 2,
        'room_id' => $roomB->id,
        'maximum_slots' => 30,
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'schedules' => [
            [
                'day_of_week' => 'Tuesday',
                'start_time' => '13:30',
                'end_time' => '15:00',
                'room_id' => $roomB->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('can_publish', false);

    $conflicts = collect($response->json('conflicts'));
    expect($conflicts->contains(fn (array $c): bool => $c['kind'] === 'faculty' && $c['severity'] === 'blocking'))->toBeTrue();
    expect($response->json('busy_overlay.faculty_busy'))->not->toBeEmpty();
});

it('detects room capacity overflow warning on preflight while keeping can_publish true', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $smallRoom = crudSurfaceRoom('Room-CAP-Small', 20);
    $subject = crudSurfaceSubject($course, ['code' => 'CAP-101', 'lecture' => 2, 'laboratory' => 0, 'units' => 2]);

    $response = $this->postJson(route('administrators.classes.workspace.preflight'), [
        'classification' => 'college',
        'school_year' => $schoolYear,
        'semester' => $semester,
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'academic_year' => 1,
        'room_id' => $smallRoom->id,
        'maximum_slots' => 35,
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'schedules' => [
            [
                'day_of_week' => 'Wednesday',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'room_id' => $smallRoom->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('can_publish', true)
        ->assertJsonPath('room_checks.0.room_id', $smallRoom->id)
        ->assertJsonPath('room_checks.0.capacity', 20)
        ->assertJsonPath('room_checks.0.fits', false);

    $conflicts = collect($response->json('conflicts'));
    expect($conflicts->contains(fn (array $c): bool => $c['kind'] === 'capacity' && $c['severity'] === 'warning'))->toBeTrue();
});

it('computes lecture and lab hours and student_impact when class_id is provided on preflight', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();

    $subject = crudSurfaceSubject($course, [
        'code' => 'HRS-101',
        'lecture' => 2,
        'laboratory' => 3,
        'units' => 3,
    ]);
    $otherSubject = crudSurfaceSubject($course, ['code' => 'HRS-OTHER']);

    $lecRoom = crudSurfaceRoom('Room-HRS-Lec', 40);
    $labRoom = crudSurfaceRoom('Room-HRS-Lab', 40, ['type' => 'laboratory']);
    $otherRoom = crudSurfaceRoom('Room-HRS-Other', 40);

    $existingClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $lecRoom->id,
        'course_codes' => [$course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 35,
    ]);

    $otherClass = Classes::factory()->create([
        'subject_id' => $otherSubject->id,
        'subject_code' => $otherSubject->code,
        'section' => 'A',
        'room_id' => $otherRoom->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 35,
    ]);

    Schedule::query()->create([
        'class_id' => $otherClass->id,
        'room_id' => $otherRoom->id,
        'day_of_week' => 'Thursday',
        'start_time' => '13:00:00',
        'end_time' => '15:00:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'component_type' => 'lecture',
        'status' => 'published',
    ]);

    $clashingStudent = crudSurfaceStudent($course, ['first_name' => 'Clash', 'last_name' => 'Student']);
    $clearStudent = crudSurfaceStudent($course, ['first_name' => 'Clear', 'last_name' => 'Student']);

    crudSurfaceEnrollment($existingClass, $clashingStudent);
    crudSurfaceEnrollment($existingClass, $clearStudent);
    crudSurfaceEnrollment($otherClass, $clashingStudent);

    $this->postJson(route('administrators.classes.workspace.preflight'), [
        'class_id' => $existingClass->id,
        'classification' => 'college',
        'school_year' => $schoolYear,
        'semester' => $semester,
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'academic_year' => 1,
        'room_id' => $lecRoom->id,
        'maximum_slots' => 35,
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'schedules' => [
            [
                'day_of_week' => 'Tuesday',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'room_id' => $lecRoom->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
            [
                'day_of_week' => 'Thursday',
                'start_time' => '13:30',
                'end_time' => '16:30',
                'room_id' => $labRoom->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lab',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('can_publish', true)
        ->assertJsonPath('hours.scheduled', 5)
        ->assertJsonPath('hours.lecture', 2)
        ->assertJsonPath('hours.lab', 3)
        ->assertJsonPath('hours.required', 5)
        ->assertJsonPath('student_impact.total_enrolled', 2)
        ->assertJsonPath('student_impact.clear_count', 1)
        ->assertJsonPath('student_impact.clashing_count', 1)
        ->assertJsonPath('student_impact.clashing_students.0.student_id', $clashingStudent->id);
});

it('returns can_publish true with zero conflicts on clean preflight', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $faculty = Faculty::factory()->create();
    $room = crudSurfaceRoom('Room-CLEAN-101', 45);
    $subject = crudSurfaceSubject($course, ['code' => 'CLEAN-101', 'lecture' => 3, 'laboratory' => 0, 'units' => 3]);

    $this->postJson(route('administrators.classes.workspace.preflight'), [
        'classification' => 'college',
        'faculty_id' => $faculty->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'academic_year' => 1,
        'room_id' => $room->id,
        'maximum_slots' => 40,
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'schedules' => [
            [
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '09:30',
                'room_id' => $room->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
            [
                'day_of_week' => 'Wednesday',
                'start_time' => '08:00',
                'end_time' => '09:30',
                'room_id' => $room->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('can_publish', true)
        ->assertJsonPath('conflicts', [])
        ->assertJsonPath('room_checks.0.fits', true)
        ->assertJsonPath('room_checks.1.fits', true);
});

// =========================================================================
// 2. POST /administrators/classes/batch-blueprint
// =========================================================================

it('detects cross-section faculty and room collisions during batch-blueprint dry_run with zero database writes', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'BATCH-DRY-101', 'lecture' => 3, 'laboratory' => 0]);
    $sharedFaculty = Faculty::factory()->create();
    $sharedRoom = crudSurfaceRoom('Room-BATCH-Shared', 40);

    $classesBefore = Classes::query()->count();
    $schedulesBefore = Schedule::query()->count();

    $response = $this->postJson(route('administrators.classes.workspace.batch-blueprint'), [
        'blueprint' => [
            'classification' => 'college',
            'course_codes' => [$course->id],
            'subject_ids' => [$subject->id],
            'subject_code' => $subject->code,
            'academic_year' => 1,
            'school_year' => $schoolYear,
            'semester' => $semester,
            'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        ],
        'sections' => [
            [
                'client_key' => 'sec-a',
                'section' => 'A',
                'faculty_id' => $sharedFaculty->id,
                'maximum_slots' => 35,
                'room_id' => $sharedRoom->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Monday',
                        'start_time' => '09:00',
                        'end_time' => '10:30',
                        'room_id' => $sharedRoom->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
            [
                'client_key' => 'sec-b',
                'section' => 'B',
                'faculty_id' => $sharedFaculty->id,
                'maximum_slots' => 35,
                'room_id' => $sharedRoom->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Monday',
                        'start_time' => '09:30',
                        'end_time' => '11:00',
                        'room_id' => $sharedRoom->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
        ],
        'dry_run' => true,
        'schedule_status' => 'published',
    ])
        ->assertSuccessful()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('dry_run', true);

    $conflicts = collect($response->json('conflicts'));
    expect($conflicts->contains(fn (array $c): bool => $c['kind'] === 'faculty' && $c['severity'] === 'blocking'))->toBeTrue();
    expect($conflicts->contains(fn (array $c): bool => $c['kind'] === 'room' && $c['severity'] === 'blocking'))->toBeTrue();

    expect(Classes::query()->count())->toBe($classesBefore);
    expect(Schedule::query()->count())->toBe($schedulesBefore);
});

it('rejects batch-blueprint publish with HTTP 422 when blocking conflicts exist and creates zero classes', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'BATCH-422-101']);
    $faculty = Faculty::factory()->create();
    $room = crudSurfaceRoom('Room-BATCH-422', 40);

    $classesBefore = Classes::query()->count();

    $this->postJson(route('administrators.classes.workspace.batch-blueprint'), [
        'blueprint' => [
            'classification' => 'college',
            'course_codes' => [$course->id],
            'subject_ids' => [$subject->id],
            'subject_code' => $subject->code,
            'academic_year' => 1,
            'school_year' => $schoolYear,
            'semester' => $semester,
            'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        ],
        'sections' => [
            [
                'client_key' => 'sec-a',
                'section' => 'A',
                'faculty_id' => $faculty->id,
                'maximum_slots' => 30,
                'room_id' => $room->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Tuesday',
                        'start_time' => '10:00',
                        'end_time' => '11:30',
                        'room_id' => $room->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
            [
                'client_key' => 'sec-b',
                'section' => 'B',
                'faculty_id' => $faculty->id,
                'maximum_slots' => 30,
                'room_id' => $room->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Tuesday',
                        'start_time' => '10:00',
                        'end_time' => '11:30',
                        'room_id' => $room->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
        ],
        'dry_run' => false,
        'schedule_status' => 'published',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('ok', false);

    expect(Classes::query()->count())->toBe($classesBefore);
});

it('saves staged sections as draft even with conflicts and atomically creates and updates sections preserving banner_image on clean publish', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $subjectDraft = crudSurfaceSubject($course, ['code' => 'BATCH-DRAFT-101', 'lecture' => 3, 'laboratory' => 0]);
    $subjectPub = crudSurfaceSubject($course, ['code' => 'BATCH-PUB-101', 'lecture' => 3, 'laboratory' => 0]);

    $faculty1 = Faculty::factory()->create();
    $faculty2 = Faculty::factory()->create();
    $faculty3 = Faculty::factory()->create();
    $room1 = crudSurfaceRoom('Room-BP-1', 40);
    $room2 = crudSurfaceRoom('Room-BP-2', 40);
    $room3 = crudSurfaceRoom('Room-BP-3', 40);

    // 1. Draft save succeeds despite cross-section room + faculty overlap
    $draftResponse = $this->postJson(route('administrators.classes.workspace.batch-blueprint'), [
        'blueprint' => [
            'classification' => 'college',
            'course_codes' => [$course->id],
            'subject_ids' => [$subjectDraft->id],
            'subject_code' => $subjectDraft->code,
            'academic_year' => 1,
            'school_year' => $schoolYear,
            'semester' => $semester,
            'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        ],
        'sections' => [
            [
                'client_key' => 'draft-a',
                'section' => 'A',
                'faculty_id' => $faculty1->id,
                'maximum_slots' => 30,
                'room_id' => $room1->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Friday',
                        'start_time' => '08:00',
                        'end_time' => '09:30',
                        'room_id' => $room1->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
            [
                'client_key' => 'draft-b',
                'section' => 'B',
                'faculty_id' => $faculty1->id,
                'maximum_slots' => 30,
                'room_id' => $room1->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Friday',
                        'start_time' => '08:00',
                        'end_time' => '09:30',
                        'room_id' => $room1->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
        ],
        'dry_run' => false,
        'schedule_status' => 'draft',
    ])
        ->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('dry_run', false);

    $draftIds = collect($draftResponse->json('results'))->pluck('class_id')->all();
    expect($draftIds)->toHaveCount(2);
    expect(Schedule::query()->whereIn('class_id', $draftIds)->pluck('status')->unique()->all())->toBe(['draft']);

    // 2. Clean published batch: atomically creates 2 new sections + updates 1 existing section preserving banner_image & custom settings
    $existingSectionA = Classes::factory()->create([
        'subject_id' => $subjectPub->id,
        'subject_ids' => [$subjectPub->id],
        'subject_code' => $subjectPub->code,
        'section' => 'A',
        'faculty_id' => $faculty1->id,
        'room_id' => $room1->id,
        'course_codes' => [$course->id],
        'academic_year' => 1,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 25,
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'banner_image' => 'class-banners/preserved-banner.png',
            'custom' => ['syllabus_url' => 'https://example.test/syllabus'],
        ]),
    ]);

    $publishResponse = $this->postJson(route('administrators.classes.workspace.batch-blueprint'), [
        'blueprint' => [
            'classification' => 'college',
            'course_codes' => [$course->id],
            'subject_ids' => [$subjectPub->id],
            'subject_code' => $subjectPub->code,
            'academic_year' => 1,
            'school_year' => $schoolYear,
            'semester' => $semester,
            'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
            'settings' => [
                'enable_online_resources' => false,
            ],
        ],
        'sections' => [
            [
                'client_key' => 'pub-a',
                'class_id' => $existingSectionA->id,
                'section' => 'A',
                'faculty_id' => $faculty1->id,
                'maximum_slots' => 35,
                'room_id' => $room1->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Monday',
                        'start_time' => '08:00',
                        'end_time' => '11:00',
                        'room_id' => $room1->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
            [
                'client_key' => 'pub-b',
                'section' => 'B',
                'faculty_id' => $faculty2->id,
                'maximum_slots' => 30,
                'room_id' => $room2->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Tuesday',
                        'start_time' => '08:00',
                        'end_time' => '11:00',
                        'room_id' => $room2->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
            [
                'client_key' => 'pub-c',
                'section' => 'C',
                'faculty_id' => $faculty3->id,
                'maximum_slots' => 40,
                'room_id' => $room3->id,
                'schedules' => [
                    [
                        'day_of_week' => 'Wednesday',
                        'start_time' => '08:00',
                        'end_time' => '11:00',
                        'room_id' => $room3->id,
                        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                        'component_type' => 'lecture',
                    ],
                ],
            ],
        ],
        'dry_run' => false,
        'schedule_status' => 'published',
    ])
        ->assertSuccessful()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('totals.sections', 3)
        ->assertJsonPath('totals.seats', 105)
        ->assertJsonPath('totals.scheduled_hours', 9)
        ->assertJsonPath('totals.required_hours', 9);

    $resultsByKey = collect($publishResponse->json('results'))->keyBy('client_key');
    expect($resultsByKey->get('pub-a')['status'])->toBe('updated');
    expect($resultsByKey->get('pub-a')['class_id'])->toBe($existingSectionA->id);
    expect($resultsByKey->get('pub-b')['status'])->toBe('created');
    expect($resultsByKey->get('pub-c')['status'])->toBe('created');
    expect($resultsByKey->get('pub-b')['class_id'])->toBeInt()->toBeGreaterThan(0);
    expect($resultsByKey->get('pub-c')['class_id'])->toBeInt()->toBeGreaterThan(0);

    $existingSectionA->refresh();
    expect((int) $existingSectionA->maximum_slots)->toBe(35);
    expect($existingSectionA->settings['banner_image'])->toBe('class-banners/preserved-banner.png');
    expect($existingSectionA->settings['custom'])->toBe(['syllabus_url' => 'https://example.test/syllabus']);
    expect($existingSectionA->settings['enable_online_resources'])->toBeFalse();
});

// =========================================================================
// 3. POST /administrators/classes/{class}/grades/verify
// =========================================================================

it('verifies, finalizes, blocks unverify until unlocked, and unverifies eligible enrollments', function (): void {
    $permission = Permission::findOrCreate('Update:Classes', 'web');
    $editor = User::factory()->create(['role' => UserRole::Registrar]);
    $editor->givePermissionTo($permission);
    $this->actingAs($editor);

    $course = Course::factory()->create();
    $class = Classes::factory()->create(['room_id' => crudSurfaceRoom('Room-GRD-101', 40)->id]);

    $gradedEnrollment = crudSurfaceEnrollment($class, crudSurfaceStudent($course), [
        'prelim_grade' => 88.0,
        'midterm_grade' => 90.0,
        'finals_grade' => 92.0,
    ]);

    $ungradedEnrollment = crudSurfaceEnrollment($class, crudSurfaceStudent($course));

    // 1. Verify all eligible enrollments (ungraded is skipped)
    $this->postJson(route('administrators.classes.workspace.grades.verify', $class), [
        'action' => 'verify',
        'notes' => 'Checked against grade sheet',
    ])
        ->assertSuccessful()
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('skipped.0.id', $ungradedEnrollment->id);

    $gradedEnrollment->refresh();
    expect((bool) $gradedEnrollment->is_grades_verified)->toBeTrue();
    expect((int) $gradedEnrollment->verified_by)->toBe($editor->id);
    expect($gradedEnrollment->verified_at)->not->toBeNull();
    expect($gradedEnrollment->verification_notes)->toBe('Checked against grade sheet');
    expect((bool) $gradedEnrollment->is_grades_finalized)->toBeFalse();

    // 2. Finalize selected enrollment
    $this->postJson(route('administrators.classes.workspace.grades.verify', $class), [
        'enrollment_ids' => [$gradedEnrollment->id],
        'action' => 'finalize',
    ])
        ->assertSuccessful()
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('skipped', []);

    $gradedEnrollment->refresh();
    expect((bool) $gradedEnrollment->is_grades_finalized)->toBeTrue();
    expect((bool) $gradedEnrollment->is_grades_verified)->toBeTrue();
    expect($gradedEnrollment->total_average)->not->toBeNull();

    // 3. Cannot unverify a finalized grade until unlocked
    $this->postJson(route('administrators.classes.workspace.grades.verify', $class), [
        'enrollment_ids' => [$gradedEnrollment->id],
        'action' => 'unverify',
    ])
        ->assertSuccessful()
        ->assertJsonPath('updated', 0)
        ->assertJsonPath('skipped.0.id', $gradedEnrollment->id);

    $gradedEnrollment->refresh();
    expect((bool) $gradedEnrollment->is_grades_verified)->toBeTrue();
    expect((bool) $gradedEnrollment->is_grades_finalized)->toBeTrue();

    // 4. Unlock finalized grade
    $this->postJson(route('administrators.classes.workspace.grades.verify', $class), [
        'enrollment_ids' => [$gradedEnrollment->id],
        'action' => 'unlock',
    ])
        ->assertSuccessful()
        ->assertJsonPath('updated', 1);

    $gradedEnrollment->refresh();
    expect((bool) $gradedEnrollment->is_grades_finalized)->toBeFalse();
    expect((bool) $gradedEnrollment->is_grades_verified)->toBeTrue();

    // 5. Now unverify succeeds
    $this->postJson(route('administrators.classes.workspace.grades.verify', $class), [
        'enrollment_ids' => [$gradedEnrollment->id],
        'action' => 'unverify',
        'notes' => 'Reopened for correction',
    ])
        ->assertSuccessful()
        ->assertJsonPath('updated', 1);

    $gradedEnrollment->refresh();
    expect((bool) $gradedEnrollment->is_grades_verified)->toBeFalse();
    expect($gradedEnrollment->verified_by)->toBeNull();
    expect($gradedEnrollment->verified_at)->toBeNull();
    expect($gradedEnrollment->verification_notes)->toBe('Reopened for correction');
});

it('forbids verifying grades for a user without Update:Classes permission', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $this->actingAs($librarian);

    $class = Classes::factory()->create(['room_id' => crudSurfaceRoom('Room-GRD-403', 30)->id]);

    $this->postJson(route('administrators.classes.workspace.grades.verify', $class), [
        'action' => 'verify',
    ])->assertForbidden();
});

// =========================================================================
// 4. POST /administrators/classes/{class}/schedule-notices/send
// =========================================================================

it('dispatches pending manual schedule change notices and returns count, recipients, and message', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $room = crudSurfaceRoom('Room-NOTIF-101', 30);

    $class = Classes::factory()->create([
        'room_id' => $room->id,
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'notify_students_on_schedule_changes' => false,
        ]),
    ]);

    $student1 = crudSurfaceStudent($course, ['email' => 'notice-one@example.test']);
    $student2 = crudSurfaceStudent($course, ['email' => 'notice-two@example.test']);
    crudSurfaceEnrollment($class, $student1);
    crudSurfaceEnrollment($class, $student2);

    $change = ClassScheduleChange::query()->create([
        'class_id' => $class->id,
        'changed_by_user_id' => $admin->id,
        'old_schedule' => [
            ['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:30', 'room_id' => $room->id, 'room_name' => $room->name],
        ],
        'new_schedule' => [
            ['day_of_week' => 'Monday', 'start_time' => '10:00', 'end_time' => '11:30', 'room_id' => $room->id, 'room_name' => $room->name],
        ],
    ]);

    $notif1 = ClassScheduleChangeStudentNotification::query()->create([
        'class_schedule_change_id' => $change->id,
        'student_id' => $student1->id,
        'email' => $student1->email,
        'auto_dispatch' => false,
    ]);

    $notif2 = ClassScheduleChangeStudentNotification::query()->create([
        'class_schedule_change_id' => $change->id,
        'student_id' => $student2->id,
        'email' => $student2->email,
        'auto_dispatch' => false,
    ]);

    $this->postJson(route('administrators.classes.workspace.schedule-notices.send', $class))
        ->assertSuccessful()
        ->assertJsonPath('count', 2)
        ->assertJsonPath('recipients', 2)
        ->assertJsonStructure(['count', 'recipients', 'message']);

    Notification::assertSentOnDemand(ClassScheduleChangedNotification::class);
    expect($notif1->fresh()->notified_at)->not->toBeNull();
    expect($notif2->fresh()->notified_at)->not->toBeNull();

    // Second call has no pending notices remaining
    $this->postJson(route('administrators.classes.workspace.schedule-notices.send', $class))
        ->assertSuccessful()
        ->assertJsonPath('count', 0)
        ->assertJsonPath('recipients', 0);
});

// =========================================================================
// 5. PATCH /administrators/classes/{class}/quick-settings
// =========================================================================

it('updates boolean policy toggles via quick-settings without wiping banner_image or custom settings', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $class = Classes::factory()->create([
        'room_id' => crudSurfaceRoom('Room-QS-101', 30)->id,
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'banner_image' => 'class-banners/keep-me.png',
            'custom' => ['room_note' => 'Bring laptop'],
            'enable_online_resources' => true,
            'allow_late_submissions' => false,
        ]),
    ]);

    $this->patchJson(route('administrators.classes.workspace.quick-settings', $class), [
        'enable_online_resources' => false,
        'allow_late_submissions' => true,
        'enable_attendance_tracking' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('settings.enable_online_resources', false)
        ->assertJsonPath('settings.allow_late_submissions', true)
        ->assertJsonPath('settings.enable_attendance_tracking', true);

    $class->refresh();
    expect($class->settings['banner_image'])->toBe('class-banners/keep-me.png');
    expect($class->settings['custom'])->toBe(['room_note' => 'Bring laptop']);
    expect($class->settings['enable_online_resources'])->toBeFalse();
    expect($class->settings['allow_late_submissions'])->toBeTrue();
    expect($class->settings['enable_attendance_tracking'])->toBeTrue();
});

it('rejects quick-settings updates for an archived class with 422', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $class = Classes::factory()->create(['room_id' => crudSurfaceRoom('Room-QS-Arch', 30)->id]);
    $class->delete();

    $this->patchJson(route('administrators.classes.workspace.quick-settings', $class), [
        'enable_online_resources' => false,
    ])->assertUnprocessable();
});

// =========================================================================
// 6. Critical Bugfix Regressions (AdministratorClassManagementController)
// =========================================================================

it('preserves banner_image on update without re-upload and deletes it when remove_banner_image is true', function (): void {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'BNR-101']);
    $bannerPath = 'class-banners/original-banner.png';
    Storage::disk('public')->put($bannerPath, 'fake-png-content');

    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'academic_year' => 1,
        'section' => 'A',
        'maximum_slots' => 35,
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'banner_image' => $bannerPath,
        ]),
    ]);

    // Update section and toggle setting with null banner_image -> preserves existing banner_image
    $this->patch(route('administrators.classes.update', $class), [
        'section' => 'B',
        'settings' => [
            'banner_image' => null,
            'enable_announcements' => false,
        ],
    ])->assertRedirect(route('administrators.classes.index'));

    $class->refresh();
    expect($class->section)->toBe('B');
    expect($class->settings['banner_image'])->toBe($bannerPath);
    expect($class->settings['enable_announcements'])->toBeFalse();
    Storage::disk('public')->assertExists($bannerPath);

    // Explicitly pass remove_banner_image: true -> removes file and clears setting
    $this->patch(route('administrators.classes.update', $class), [
        'remove_banner_image' => true,
    ])->assertRedirect(route('administrators.classes.index'));

    $class->refresh();
    expect($class->settings['banner_image'])->toBeNull();
    Storage::disk('public')->assertMissing($bannerPath);
});

it('rejects invalid banner file types on store and update', function (string $filename): void {
    Storage::fake('public');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'BAD-BNR-101']);

    $invalidFile = UploadedFile::fake()->create($filename, 12);

    // Reject on store
    $this->post(route('administrators.classes.store'), [
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'academic_year' => 1,
        'semester' => (string) $period->getCurrentSemester(),
        'school_year' => $period->getCurrentSchoolYearString(),
        'section' => 'A',
        'maximum_slots' => 30,
        'settings' => [
            'banner_image' => $invalidFile,
        ],
    ])->assertSessionHasErrors('settings.banner_image');

    // Reject on update
    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'academic_year' => 1,
        'maximum_slots' => 30,
    ]);

    $this->patch(route('administrators.classes.update', $class), [
        'settings' => [
            'banner_image' => UploadedFile::fake()->create($filename, 12),
        ],
    ])->assertSessionHasErrors('settings.banner_image');
})->with([
    'php script' => 'shell.php',
    'plain text' => 'notes.txt',
]);

it('blocks shrinking maximum_slots below active enrolled count unless allow_below_enrolled is true and ignores dropped or trashed enrollments', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'CAP-GRD-101']);

    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'academic_year' => 1,
        'maximum_slots' => 10,
    ]);

    // 3 active enrolled
    for ($i = 0; $i < 3; $i++) {
        crudSurfaceEnrollment($class, crudSurfaceStudent($course));
    }

    // 2 dropped enrollments
    for ($i = 0; $i < 2; $i++) {
        crudSurfaceEnrollment($class, crudSurfaceStudent($course), [
            'status' => false,
            'enrollment_status' => ClassEnrollment::STATUS_DROPPED,
        ]);
    }

    // 1 soft-deleted enrollment
    $trashedEnrollment = crudSurfaceEnrollment($class, crudSurfaceStudent($course));
    $trashedEnrollment->delete();

    // Shrinking to 2 (< 3 active) without override is blocked
    $this->patch(route('administrators.classes.update', $class), [
        'maximum_slots' => 2,
    ])->assertSessionHasErrors('maximum_slots');

    expect((int) $class->fresh()->maximum_slots)->toBe(10);

    // Shrinking to 3 (== 3 active, even though 6 total rows with dropped/trashed) succeeds
    $this->patch(route('administrators.classes.update', $class), [
        'maximum_slots' => 3,
    ])->assertRedirect(route('administrators.classes.index'));

    expect((int) $class->fresh()->maximum_slots)->toBe(3);

    // Shrinking to 2 WITH allow_below_enrolled: true succeeds
    $this->patch(route('administrators.classes.update', $class), [
        'maximum_slots' => 2,
        'allow_below_enrolled' => true,
    ])->assertRedirect(route('administrators.classes.index'));

    expect((int) $class->fresh()->maximum_slots)->toBe(2);
});

it('stores a class with custom section code STEM-1, start_date, and enable_online_resources false', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'CUST-101']);

    $this->post(route('administrators.classes.store'), [
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'academic_year' => 1,
        'semester' => (string) $period->getCurrentSemester(),
        'school_year' => $period->getCurrentSchoolYearString(),
        'start_date' => '2026-08-17',
        'section' => 'STEM-1',
        'maximum_slots' => 40,
        'settings' => [
            'enable_online_resources' => false,
        ],
    ])->assertRedirect(route('administrators.classes.index'));

    $created = Classes::query()->where('section', 'STEM-1')->latest('id')->firstOrFail();
    expect($created->section)->toBe('STEM-1');
    expect($created->start_date?->format('Y-m-d'))->toBe('2026-08-17');
    expect($created->settings['enable_online_resources'])->toBeFalse();
});

it('clears opposite classification columns when switching between college and shs on update', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'SW-COL-101', 'academic_year' => 2]);

    $track = ShsTrack::query()->create(['track_name' => 'Academic Track']);
    $strand = ShsStrand::query()->create(['strand_name' => 'STEM', 'track_id' => $track->id]);
    $shsSubject = StrandSubject::query()->create([
        'code' => 'SHS-CALC11',
        'title' => 'Pre-Calculus',
        'grade_year' => 11,
        'semester' => 1,
        'strand_id' => $strand->id,
    ]);

    $class = Classes::factory()->create([
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'academic_year' => 2,
        'shs_track_id' => null,
        'shs_strand_id' => null,
        'grade_level' => null,
    ]);

    // 1. Switch college -> shs
    $this->patch(route('administrators.classes.update', $class), [
        'classification' => 'shs',
        'shs_track_id' => $track->id,
        'shs_strand_id' => $strand->id,
        'grade_level' => 'Grade 11',
        'subject_code_shs' => $shsSubject->code,
    ])->assertRedirect(route('administrators.classes.index'));

    $class->refresh();
    expect($class->classification)->toBe('shs');
    expect((int) $class->shs_track_id)->toBe($track->id);
    expect((int) $class->shs_strand_id)->toBe($strand->id);
    expect($class->grade_level)->toBe('Grade 11');
    expect($class->subject_code)->toBe($shsSubject->code);
    expect($class->course_codes)->toBeNull();
    expect($class->subject_ids)->toBeNull();
    expect($class->subject_id)->toBeNull();
    expect($class->academic_year)->toBeNull();

    // 2. Switch shs -> college
    $this->patch(route('administrators.classes.update', $class), [
        'classification' => 'college',
        'course_codes' => [$course->id],
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'academic_year' => 2,
    ])->assertRedirect(route('administrators.classes.index'));

    $class->refresh();
    expect($class->classification)->toBe('college');
    expect($class->course_codes)->toBe([$course->id]);
    expect($class->subject_ids)->toBe([$subject->id]);
    expect((int) $class->subject_id)->toBe($subject->id);
    expect((int) $class->academic_year)->toBe(2);
    expect($class->shs_track_id)->toBeNull();
    expect($class->shs_strand_id)->toBeNull();
    expect($class->grade_level)->toBeNull();
});

it('reports active seat counts excluding dropped and trashed enrollments, uncapped transferable siblings, and sibling is_current flags on show', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $subject = crudSurfaceSubject($course, ['code' => 'DOS-101', 'title' => 'Dossier Subject']);

    $currentClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'section' => 'A',
        'course_codes' => [$course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    // Uncapped sibling (maximum_slots = 0) with 2 enrolled students -> must NOT be full
    $uncappedSibling = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'section' => 'B',
        'course_codes' => [$course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 0,
    ]);

    // Capped full sibling (1/1)
    $fullSibling = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'section' => 'C',
        'course_codes' => [$course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 1,
    ]);

    // Current class: 2 active + 1 dropped + 1 soft-deleted
    crudSurfaceEnrollment($currentClass, crudSurfaceStudent($course));
    crudSurfaceEnrollment($currentClass, crudSurfaceStudent($course));
    crudSurfaceEnrollment($currentClass, crudSurfaceStudent($course), [
        'status' => false,
        'enrollment_status' => ClassEnrollment::STATUS_DROPPED,
    ]);
    $trashed = crudSurfaceEnrollment($currentClass, crudSurfaceStudent($course));
    $trashed->delete();

    // Uncapped sibling enrollments
    crudSurfaceEnrollment($uncappedSibling, crudSurfaceStudent($course));
    crudSurfaceEnrollment($uncappedSibling, crudSurfaceStudent($course));

    // Full sibling enrollment
    crudSurfaceEnrollment($fullSibling, crudSurfaceStudent($course));

    $this->get(route('administrators.classes.show', $currentClass))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/show', false)
            ->where('class.students_count', 2)
            ->where('dossier.health.seats.active', 2)
            ->where('transferable_classes', function ($transferables) use ($uncappedSibling, $fullSibling): bool {
                $byId = collect($transferables)->keyBy('id');
                $uncapped = $byId->get($uncappedSibling->id);
                $full = $byId->get($fullSibling->id);

                return $uncapped !== null
                    && $uncapped['is_full'] === false
                    && $uncapped['available_slots'] === null
                    && ! str_contains((string) $uncapped['label'], '[FULL]')
                    && $full !== null
                    && $full['is_full'] === true
                    && $full['available_slots'] === 0;
            })
            ->where('dossier.sibling_sections', function ($siblings) use ($currentClass, $uncappedSibling, $fullSibling): bool {
                $byId = collect($siblings)->keyBy('id');

                return $byId->count() === 3
                    && $byId->get($currentClass->id)['is_current'] === true
                    && $byId->get($uncappedSibling->id)['is_current'] === false
                    && $byId->get($fullSibling->id)['is_current'] === false;
            })
        );
});

it('defaults copy() to not copying schedules and demotes conflicting blocks to draft when copy_schedules is true', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = (string) $period->getCurrentSemester();
    $course = Course::factory()->create();
    $faculty = Faculty::factory()->create();
    $room = crudSurfaceRoom('Room-COPY-101', 40);
    $subject = crudSurfaceSubject($course, ['code' => 'CPY-101']);

    $sourceClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_ids' => [$subject->id],
        'subject_code' => $subject->code,
        'section' => 'A',
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'course_codes' => [$course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 40,
    ]);

    Schedule::query()->create([
        'class_id' => $sourceClass->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'component_type' => 'lecture',
        'status' => 'published',
    ]);

    // 1. Copy without copy_schedules -> schedules are NOT copied
    $this->post(route('administrators.classes.copy', $sourceClass), [
        'section' => 'B',
    ])->assertRedirect(route('administrators.classes.index'));

    $copiedNoSched = Classes::query()
        ->where('subject_code', $subject->code)
        ->where('section', 'B')
        ->firstOrFail();

    expect($copiedNoSched->schedules()->count())->toBe(0);

    // 2. Copy with copy_schedules: true -> conflicting copied block is demoted to draft
    $this->post(route('administrators.classes.copy', $sourceClass), [
        'section' => 'C',
        'copy_schedules' => true,
    ])->assertRedirect(route('administrators.classes.index'));

    $copiedWithSched = Classes::query()
        ->where('subject_code', $subject->code)
        ->where('section', 'C')
        ->firstOrFail();

    $copiedSchedules = $copiedWithSched->schedules()->get();
    expect($copiedSchedules)->toHaveCount(1);
    expect($copiedSchedules->first()->status)->toBe('draft');
});
