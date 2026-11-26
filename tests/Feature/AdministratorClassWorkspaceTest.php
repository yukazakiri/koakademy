<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassScheduleChange;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Notifications\ClassScheduleChangedNotification;
use App\Services\GeneralSettingsService;
use App\Services\GradeComputationService;
use App\Services\Scheduling\SchedulingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

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
 * Helper to create a clean Student without triggering document_locations FK issues.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeWorkspaceStudent(Course $course, array $overrides = []): Student
{
    return Student::factory()->create(array_merge([
        'course_id' => $course->id,
        'document_location_id' => null,
    ], $overrides));
}

/**
 * Helper to create a clean Room with a unique name and explicit capacity.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeWorkspaceRoom(string $name, int $capacity = 40, array $overrides = []): Room
{
    return Room::query()->create(array_merge([
        'name' => $name,
        'class_code' => 'LEC',
        'capacity' => $capacity,
        'is_active' => true,
    ], $overrides));
}

/**
 * Helper to create an ungraded ClassEnrollment so ClassEnrollmentFactory randomness never marks it grade-protected.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeCleanEnrollment(Classes $class, Student $student, array $overrides = []): ClassEnrollment
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
        'is_grades_verified' => false,
        'is_grades_finalized' => false,
        'remarks' => null,
    ], $overrides));
}

// =========================================================================
// 1. Index enrichment & bug fixes
// =========================================================================

it('enriches administrator classes index rows with room, schedules, schedule_health, waitlist_count, and schedule_revision', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $course = Course::factory()->create();
    $room = makeWorkspaceRoom('IT-Lab-201', 35);
    $subject = Subject::factory()->create([
        'code' => 'CS-ENRICH',
        'title' => 'Data Structures',
        'lecture' => 3,
        'laboratory' => 0,
        'units' => 3,
    ]);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $room->id,
        'course_codes' => [(string) $course->id],
        'school_year' => $period->getCurrentSchoolYearString(),
        'semester' => $period->getCurrentSemester(),
        'classification' => 'college',
        'maximum_slots' => 35,
    ]);

    Schedule::query()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '09:00:00',
        'end_time' => '10:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'component_type' => 'lecture',
        'status' => 'published',
    ]);

    $expectedRevision = app(SchedulingService::class)->revision($class);

    $this->get(route('administrators.classes.index', ['search' => 'CS-ENRICH']))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->has('classes.data', 1)
            ->where('classes.data.0.id', $class->id)
            ->where('classes.data.0.room', 'IT-Lab-201')
            ->where('classes.data.0.room_capacity', 35)
            ->has('classes.data.0.schedules', 1)
            ->where('classes.data.0.schedules.0.day_of_week', 'Monday')
            ->where('classes.data.0.schedules.0.room_name', 'IT-Lab-201')
            ->where('classes.data.0.schedule_health.status', 'published')
            ->where('classes.data.0.schedule_health.has_conflict', false)
            ->where('classes.data.0.waitlist_count', 0)
            ->where('classes.data.0.schedule_revision', $expectedRevision)
        );
});

it('returns resolved string enrollment status, is_grade_protected, and schedule_revision for selected class on index', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $course = Course::factory()->create();
    $room = makeWorkspaceRoom('Room-SEL-101', 40);
    $subject = Subject::factory()->create(['code' => 'CS-SEL101', 'title' => 'Algorithms']);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $room->id,
        'course_codes' => [(string) $course->id],
        'school_year' => $period->getCurrentSchoolYearString(),
        'semester' => $period->getCurrentSemester(),
        'classification' => 'college',
        'maximum_slots' => 40,
    ]);

    $enrolledStudent = makeWorkspaceStudent($course, ['first_name' => 'Elena', 'last_name' => 'Cruz']);
    $droppedStudent = makeWorkspaceStudent($course, ['first_name' => 'Marco', 'last_name' => 'Reyes']);

    $activeEnrollment = makeCleanEnrollment($class, $enrolledStudent, [
        'status' => true,
        'enrollment_status' => ClassEnrollment::STATUS_ENROLLED,
        'is_grades_verified' => true,
    ]);

    $droppedEnrollment = makeCleanEnrollment($class, $droppedStudent, [
        'status' => false,
        'enrollment_status' => ClassEnrollment::STATUS_DROPPED,
        'is_grades_verified' => false,
    ]);

    $expectedRevision = app(SchedulingService::class)->revision($class);

    $this->get(route('administrators.classes.index', ['selected' => $class->id]))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/classes/index', false)
            ->where('selected_class.id', $class->id)
            ->where('selected_class.schedule_revision', $expectedRevision)
            ->has('selected_class.enrollments', 2)
            ->where('selected_class.enrollments', function ($enrollments) use ($activeEnrollment, $droppedEnrollment): bool {
                $byId = collect($enrollments)->keyBy('id');

                $active = $byId->get($activeEnrollment->id);
                $dropped = $byId->get($droppedEnrollment->id);

                return $active !== null
                    && $dropped !== null
                    && $active['status'] === 'enrolled'
                    && $active['is_grade_protected'] === true
                    && $dropped['status'] === 'dropped'
                    && $dropped['is_grade_protected'] === false;
            })
        );
});

// =========================================================================
// 2. Workspace show (GET /administrators/classes/{class}/workspace)
// =========================================================================

it('returns full sorted roster beyond 30 students and workspace metadata on workspace show', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = $period->getCurrentSemester();
    $course = Course::factory()->create();
    $room = makeWorkspaceRoom('Hall-WS-301', 50);
    $faculty = Faculty::factory()->create();
    $subject = Subject::factory()->create(['code' => 'CS-WS301', 'title' => 'Operating Systems']);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
        'course_codes' => [(string) $course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 50,
    ]);

    $sibling = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'B',
        'faculty_id' => Faculty::factory()->create()->id,
        'room_id' => makeWorkspaceRoom('Hall-WS-302', 40)->id,
        'course_codes' => [(string) $course->id],
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 40,
    ]);

    Schedule::query()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:00:00',
        'end_time' => '11:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'component_type' => 'lecture',
        'status' => 'published',
    ]);

    // Create 34 students (more than the old 30-row cap) with deliberate unsorted last names
    $lastNames = [];
    for ($i = 34; $i >= 1; $i--) {
        $lastName = sprintf('StudentLast%02d', $i);
        $lastNames[] = $lastName;
        $student = makeWorkspaceStudent($course, [
            'first_name' => 'Test',
            'last_name' => $lastName,
        ]);

        makeCleanEnrollment($class, $student, [
            'enrollment_status' => $i === 1 ? ClassEnrollment::STATUS_AUDIT : ClassEnrollment::STATUS_ENROLLED,
            'prelim_grade' => $i === 1 ? 92.5 : null,
        ]);
    }

    $response = $this->getJson(route('administrators.classes.workspace.show', $class))
        ->assertSuccessful()
        ->assertJsonPath('class.id', $class->id)
        ->assertJsonPath('class.students_count', 34)
        ->assertJsonPath('sibling_sections.0.id', $sibling->id)
        ->assertJsonPath('sibling_sections.0.section', 'B')
        ->assertJsonStructure([
            'class',
            'enrollments',
            'schedule_summary',
            'busy_overlay' => ['faculty_busy', 'room_busy', 'cohort_busy'],
            'resolutions',
            'student_impact' => ['total_enrolled', 'clear_count', 'clashing_count', 'clashing_students'],
            'sibling_sections',
            'schedule_revision',
        ]);

    $enrollments = $response->json('enrollments');
    expect($enrollments)->toHaveCount(34);

    // First student alphabetically is StudentLast01 (audit + recorded prelim grade)
    expect($enrollments[0]['student']['name'])->toContain('StudentLast01');
    expect($enrollments[0]['enrollment_status'])->toBe(ClassEnrollment::STATUS_AUDIT);
    expect($enrollments[0]['is_grade_protected'])->toBeTrue();

    // Last student alphabetically is StudentLast34 (ungraded)
    expect($enrollments[33]['student']['name'])->toContain('StudentLast34');
    expect($enrollments[33]['enrollment_status'])->toBe(ClassEnrollment::STATUS_ENROLLED);
    expect($enrollments[33]['is_grade_protected'])->toBeFalse();
});

it('forbids non-admin users from accessing the class workspace endpoint', function (): void {
    $studentUser = User::factory()->create(['role' => UserRole::Student]);
    $room = makeWorkspaceRoom('Room-AUTH-101', 30);
    $class = Classes::factory()->create(['room_id' => $room->id]);

    $this->actingAs($studentUser)
        ->getJson(route('administrators.classes.workspace.show', $class))
        ->assertForbidden();
});

// =========================================================================
// 3. Preflight student search (GET /administrators/classes/{class}/students/search)
// =========================================================================

it('detects already enrolled, sibling transfer, schedule clash, and capacity overload preflight states during student search', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = $period->getCurrentSemester();
    $course = Course::factory()->create();

    $subject = Subject::factory()->create(['code' => 'CS-PRE101', 'title' => 'Preflight Course']);
    $otherSubject = Subject::factory()->create(['code' => 'MATH-CLASH', 'title' => 'Clashing Math']);

    $roomA = makeWorkspaceRoom('Room-PRE-A', 30);
    $roomB = makeWorkspaceRoom('Room-PRE-B', 30);
    $roomOther = makeWorkspaceRoom('Room-PRE-C', 30);

    // Open target class (slots = 10) for already_enrolled, sibling transfer, and clash detection
    $targetClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $roomA->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 10,
    ]);

    Schedule::query()->create([
        'class_id' => $targetClass->id,
        'room_id' => $roomA->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    // Sibling section B (same subject, different non-overlapping time)
    $siblingClass = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'B',
        'room_id' => $roomB->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 10,
    ]);

    // Other subject class overlapping Monday 08:30-10:00
    $clashingClass = Classes::factory()->create([
        'subject_id' => $otherSubject->id,
        'subject_code' => $otherSubject->code,
        'section' => 'A',
        'room_id' => $roomOther->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    Schedule::query()->create([
        'class_id' => $clashingClass->id,
        'room_id' => $roomOther->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:30:00',
        'end_time' => '10:00:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    $alreadyHere = makeWorkspaceStudent($course, ['first_name' => 'Alpha', 'last_name' => 'PreflightSearch']);
    $inSibling = makeWorkspaceStudent($course, ['first_name' => 'Beta', 'last_name' => 'PreflightSearch']);
    $hasClash = makeWorkspaceStudent($course, ['first_name' => 'Gamma', 'last_name' => 'PreflightSearch']);
    $cleanCandidate = makeWorkspaceStudent($course, ['first_name' => 'Delta', 'last_name' => 'PreflightSearch']);

    makeCleanEnrollment($targetClass, $alreadyHere);
    $siblingEnrollment = makeCleanEnrollment($siblingClass, $inSibling);
    makeCleanEnrollment($clashingClass, $hasClash);

    $response = $this->getJson(route('administrators.classes.workspace.students.search', [
        'class' => $targetClass->id,
        'q' => 'PreflightSearch',
    ]))
        ->assertSuccessful();

    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId->get($alreadyHere->id))->toMatchArray([
        'already_enrolled_here' => true,
        'recommended_action' => 'already_enrolled',
    ]);

    expect($byId->get($inSibling->id))->toMatchArray([
        'already_enrolled_here' => false,
        'recommended_action' => 'transfer',
        'sibling_section_enrollment' => [
            'class_id' => $siblingClass->id,
            'section' => 'B',
            'enrollment_id' => $siblingEnrollment->id,
        ],
    ]);

    expect($byId->get($hasClash->id)['recommended_action'])->toBe('blocked_clash');
    expect($byId->get($hasClash->id)['schedule_conflicts'])->not->toBeEmpty();

    expect($byId->get($cleanCandidate->id))->toMatchArray([
        'already_enrolled_here' => false,
        'would_exceed_capacity' => false,
        'recommended_action' => 'enroll',
    ]);

    // Now test capacity overload detection when class is full (1/1)
    $targetClass->update(['maximum_slots' => 1]);

    $fullResponse = $this->getJson(route('administrators.classes.workspace.students.search', [
        'class' => $targetClass->id,
        'q' => 'Delta PreflightSearch',
    ]))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $cleanCandidate->id)
        ->assertJsonPath('data.0.would_exceed_capacity', true)
        ->assertJsonPath('data.0.recommended_action', 'overload_required');

    expect($fullResponse->json('data.0.is_class_full'))->toBeTrue();
});

// =========================================================================
// 4. Enroll students (POST /administrators/classes/{class}/enrollments)
// =========================================================================

it('enrolls a student cleanly and enforces capacity guard with force_overload override', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $course = Course::factory()->create();
    $room = makeWorkspaceRoom('Room-ENR-101', 30);
    $subject = Subject::factory()->create(['code' => 'CS-ENR101', 'title' => 'Programming 1']);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $room->id,
        'school_year' => $period->getCurrentSchoolYearString(),
        'semester' => $period->getCurrentSemester(),
        'classification' => 'college',
        'maximum_slots' => 1,
    ]);

    $firstStudent = makeWorkspaceStudent($course);
    $secondStudent = makeWorkspaceStudent($course);

    // First student enrolls cleanly
    $this->postJson(route('administrators.classes.workspace.enrollments.store', $class), [
        'student_ids' => [$firstStudent->id],
    ])
        ->assertSuccessful()
        ->assertJsonPath('result.enrolled.0.student_id', $firstStudent->id)
        ->assertJsonPath('result.enrolled.0.enrollment_status', ClassEnrollment::STATUS_ENROLLED);

    $firstEnrollment = ClassEnrollment::query()
        ->where('class_id', $class->id)
        ->where('student_id', $firstStudent->id)
        ->firstOrFail();

    expect((bool) $firstEnrollment->status)->toBeTrue();
    expect($firstEnrollment->enrollment_status)->toBe(ClassEnrollment::STATUS_ENROLLED);

    // Second student rejected without force_overload + override_reason
    $this->postJson(route('administrators.classes.workspace.enrollments.store', $class), [
        'student_ids' => [$secondStudent->id],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['force_overload']);

    // Second student rejected when force_overload is true but override_reason is missing
    $this->postJson(route('administrators.classes.workspace.enrollments.store', $class), [
        'student_ids' => [$secondStudent->id],
        'force_overload' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['force_overload']);

    // Second student succeeds when force_overload = true and override_reason is provided
    $this->postJson(route('administrators.classes.workspace.enrollments.store', $class), [
        'student_ids' => [$secondStudent->id],
        'force_overload' => true,
        'override_reason' => 'Dean approved graduating senior overload',
    ])
        ->assertSuccessful()
        ->assertJsonPath('result.enrolled.0.student_id', $secondStudent->id)
        ->assertJsonPath('result.enrolled.0.overrides.0', 'capacity_overload');

    expect(ClassEnrollment::query()->where('class_id', $class->id)->where('student_id', $secondStudent->id)->exists())->toBeTrue();
});

it('transfers a student from a sibling section and writes the section code into subject_enrollments.section', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = $period->getCurrentSemester();
    $school = School::query()->first() ?? School::factory()->create();
    $course = Course::factory()->create();
    $subject = Subject::factory()->create(['code' => 'CS-XFER', 'title' => 'Section Transfer Subject']);

    $sectionA = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => makeWorkspaceRoom('Room-XFER-A', 30)->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    $sectionB = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'B',
        'room_id' => makeWorkspaceRoom('Room-XFER-B', 30)->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    $student = makeWorkspaceStudent($course);
    $classEnrollment = makeCleanEnrollment($sectionA, $student);

    $studentEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_id' => $school->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
    ]);

    $subjectEnrollment = SubjectEnrollment::query()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'class_id' => $sectionA->id,
        'enrollment_id' => $studentEnrollment->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'academic_year' => 1,
        'classification' => 'internal',
        'section' => 'A',
        'school_id' => $school->id,
    ]);

    $this->postJson(route('administrators.classes.workspace.enrollments.store', $sectionB), [
        'student_ids' => [$student->id],
        'allow_transfer_from_sibling' => true,
    ])
        ->assertSuccessful()
        ->assertJsonPath('result.transferred.0.student_id', $student->id)
        ->assertJsonPath('result.transferred.0.from_class_id', $sectionA->id)
        ->assertJsonPath('result.transferred.0.to_class_id', $sectionB->id)
        ->assertJsonPath('result.transferred.0.to_section', 'B');

    $classEnrollment->refresh();
    $subjectEnrollment->refresh();

    expect((int) $classEnrollment->class_id)->toBe($sectionB->id);
    expect((int) $subjectEnrollment->class_id)->toBe($sectionB->id);
    // Regression guard: StudentSectionTransferService must store section name ('B'), never the integer class ID
    expect($subjectEnrollment->section)->toBe('B');
    expect($subjectEnrollment->section)->not->toBe((string) $sectionB->id);
});

// =========================================================================
// 5. Inline enrollment edit, grade-lock drop protection, and restore
// =========================================================================

it('updates enrollment status, remarks, and period grades with auto-computed total_average', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $class = Classes::factory()->create(['room_id' => makeWorkspaceRoom('Room-EDIT-101', 30)->id]);
    $student = makeWorkspaceStudent($course);
    $enrollment = makeCleanEnrollment($class, $student);

    // Switch to incomplete (remains active: status = true) and record remarks + grades
    $expectedAverage = number_format(
        app(GradeComputationService::class)->totalAverage(88.0, 90.0, 94.0),
        2,
        '.',
        '',
    );

    $this->patchJson(route('administrators.classes.workspace.enrollments.update', [$class, $enrollment]), [
        'enrollment_status' => ClassEnrollment::STATUS_INCOMPLETE,
        'remarks' => 'Missing final lab submission',
        'prelim_grade' => 88,
        'midterm_grade' => 90,
        'finals_grade' => 94,
    ])
        ->assertSuccessful()
        ->assertJsonPath('enrollment.enrollment_status', ClassEnrollment::STATUS_INCOMPLETE)
        ->assertJsonPath('enrollment.status', true)
        ->assertJsonPath('enrollment.remarks', 'Missing final lab submission')
        ->assertJsonPath('enrollment.total_average', $expectedAverage);

    // Switch to audit (still active: status = true)
    $this->patchJson(route('administrators.classes.workspace.enrollments.update', [$class, $enrollment]), [
        'enrollment_status' => ClassEnrollment::STATUS_AUDIT,
    ])
        ->assertSuccessful()
        ->assertJsonPath('enrollment.enrollment_status', ClassEnrollment::STATUS_AUDIT)
        ->assertJsonPath('enrollment.status', true);

    // Create an ungraded enrollment and drop it via PATCH (status syncs to false)
    $ungradedStudent = makeWorkspaceStudent($course);
    $ungradedEnrollment = makeCleanEnrollment($class, $ungradedStudent);

    $this->patchJson(route('administrators.classes.workspace.enrollments.update', [$class, $ungradedEnrollment]), [
        'enrollment_status' => ClassEnrollment::STATUS_DROPPED,
        'status_reason' => 'Student withdrew before prelims',
    ])
        ->assertSuccessful()
        ->assertJsonPath('enrollment.enrollment_status', ClassEnrollment::STATUS_DROPPED)
        ->assertJsonPath('enrollment.status', false);

    $ungradedEnrollment->refresh();
    expect((bool) $ungradedEnrollment->status)->toBeFalse();
    expect($ungradedEnrollment->enrollment_status)->toBe(ClassEnrollment::STATUS_DROPPED);
});

it('blocks dropping a grade-protected enrollment without override and allows override plus restore', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $course = Course::factory()->create();
    $class = Classes::factory()->create([
        'room_id' => makeWorkspaceRoom('Room-DROP-101', 30)->id,
        'maximum_slots' => 30,
    ]);
    $student = makeWorkspaceStudent($course);

    $enrollment = makeCleanEnrollment($class, $student, [
        'prelim_grade' => 85.0,
        'is_grades_verified' => true,
    ]);

    // Blocked without override
    $this->deleteJson(route('administrators.classes.workspace.enrollments.destroy', [$class, $enrollment]), [
        'reason' => 'Attempted normal drop',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['force_grade_override']);

    // Blocked when force_grade_override is true but reason is missing
    $this->deleteJson(route('administrators.classes.workspace.enrollments.destroy', [$class, $enrollment]), [
        'force_grade_override' => true,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['force_grade_override']);

    // Succeeds with force_grade_override = true + reason
    $this->deleteJson(route('administrators.classes.workspace.enrollments.destroy', [$class, $enrollment]), [
        'force_grade_override' => true,
        'reason' => 'Registrar approved retroactive administrative drop',
    ])
        ->assertSuccessful()
        ->assertJsonPath('enrollment.enrollment_status', ClassEnrollment::STATUS_DROPPED)
        ->assertJsonPath('enrollment.status', false);

    $enrollment->refresh();
    expect((bool) $enrollment->status)->toBeFalse();
    expect($enrollment->enrollment_status)->toBe(ClassEnrollment::STATUS_DROPPED);

    // Restore dropped enrollment back to enrolled (status = true)
    $this->postJson(route('administrators.classes.workspace.enrollments.restore', [
        'class' => $class->id,
        'enrollmentId' => $enrollment->id,
    ]), [
        'reason' => 'Student reinstated by registrar',
    ])
        ->assertSuccessful()
        ->assertJsonPath('enrollment.enrollment_status', ClassEnrollment::STATUS_ENROLLED)
        ->assertJsonPath('enrollment.status', true);

    $enrollment->refresh();
    expect((bool) $enrollment->status)->toBeTrue();
    expect($enrollment->enrollment_status)->toBe(ClassEnrollment::STATUS_ENROLLED);
});

// =========================================================================
// 6. Atomic bulk move (POST /administrators/classes/{class}/enrollments/bulk-move)
// =========================================================================

it('bulk-moves eligible students to a sibling section while reporting grade-locked and clashing students', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = $period->getCurrentSemester();
    $course = Course::factory()->create();

    $subject = Subject::factory()->create(['code' => 'CS-BMOVE', 'title' => 'Bulk Move Subject']);
    $otherSubject = Subject::factory()->create(['code' => 'ENG-CLASH', 'title' => 'Clashing English']);

    $roomA = makeWorkspaceRoom('Room-BM-A', 30);
    $roomB = makeWorkspaceRoom('Room-BM-B', 30);
    $roomOther = makeWorkspaceRoom('Room-BM-C', 30);

    $sectionA = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $roomA->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    $sectionB = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'B',
        'room_id' => $roomB->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    Schedule::query()->create([
        'class_id' => $sectionB->id,
        'room_id' => $roomB->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '13:00:00',
        'end_time' => '14:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    $clashingOtherClass = Classes::factory()->create([
        'subject_id' => $otherSubject->id,
        'subject_code' => $otherSubject->code,
        'section' => 'A',
        'room_id' => $roomOther->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    Schedule::query()->create([
        'class_id' => $clashingOtherClass->id,
        'room_id' => $roomOther->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '13:30:00',
        'end_time' => '15:00:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    $eligible1 = makeWorkspaceStudent($course);
    $eligible2 = makeWorkspaceStudent($course);
    $gradeLocked = makeWorkspaceStudent($course);
    $clashing = makeWorkspaceStudent($course);

    $e1 = makeCleanEnrollment($sectionA, $eligible1);
    $e2 = makeCleanEnrollment($sectionA, $eligible2);
    $eLocked = makeCleanEnrollment($sectionA, $gradeLocked, ['is_grades_verified' => true]);
    $eClash = makeCleanEnrollment($sectionA, $clashing);
    makeCleanEnrollment($clashingOtherClass, $clashing);

    $response = $this->postJson(route('administrators.classes.workspace.enrollments.bulk-move', $sectionA), [
        'target_class_id' => $sectionB->id,
        'student_ids' => [$eligible1->id, $eligible2->id, $gradeLocked->id, $clashing->id],
        'reason' => 'Rebalancing sections A and B',
    ])
        ->assertSuccessful()
        ->assertJsonPath('result.source_class_id', $sectionA->id)
        ->assertJsonPath('result.target_class_id', $sectionB->id)
        ->assertJsonPath('result.moved_count', 2)
        ->assertJsonPath('result.error_count', 2);

    $errorsByStudentId = collect($response->json('result.errors'))->keyBy('student_id');
    expect($errorsByStudentId->get($gradeLocked->id)['code'])->toBe('grades_locked');
    expect($errorsByStudentId->get($clashing->id)['code'])->toBe('schedule_conflict');

    expect((int) $e1->fresh()->class_id)->toBe($sectionB->id);
    expect((int) $e2->fresh()->class_id)->toBe($sectionB->id);
    expect((int) $eLocked->fresh()->class_id)->toBe($sectionA->id);
    expect((int) $eClash->fresh()->class_id)->toBe($sectionA->id);
});

// =========================================================================
// 7. Schedule impact preview & Draft vs. Publish workspace save
// =========================================================================

it('detects enrolled-student timetable clashes on schedule impact preview', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = $period->getCurrentSemester();
    $course = Course::factory()->create();

    $subjectA = Subject::factory()->create(['code' => 'CS-IMP', 'title' => 'Impact Target']);
    $subjectB = Subject::factory()->create(['code' => 'PHY-101', 'title' => 'General Physics']);

    $roomA = makeWorkspaceRoom('Room-IMP-A', 30);
    $roomB = makeWorkspaceRoom('Room-IMP-B', 30);

    $targetClass = Classes::factory()->create([
        'subject_id' => $subjectA->id,
        'subject_code' => $subjectA->code,
        'section' => 'A',
        'room_id' => $roomA->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
    ]);

    $otherClass = Classes::factory()->create([
        'subject_id' => $subjectB->id,
        'subject_code' => $subjectB->code,
        'section' => 'C',
        'room_id' => $roomB->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
    ]);

    Schedule::query()->create([
        'class_id' => $otherClass->id,
        'room_id' => $roomB->id,
        'day_of_week' => 'Thursday',
        'start_time' => '10:00:00',
        'end_time' => '11:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    $clashingStudent = makeWorkspaceStudent($course, ['first_name' => 'Clashing', 'last_name' => 'Learner']);
    $clearStudent = makeWorkspaceStudent($course, ['first_name' => 'Clear', 'last_name' => 'Learner']);

    makeCleanEnrollment($targetClass, $clashingStudent);
    makeCleanEnrollment($targetClass, $clearStudent);
    makeCleanEnrollment($otherClass, $clashingStudent);

    $this->postJson(route('administrators.classes.workspace.schedule.impact', $targetClass), [
        'schedules' => [
            [
                'day_of_week' => 'Thursday',
                'start_time' => '10:30',
                'end_time' => '12:00',
                'room_id' => $roomA->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('student_impact.total_enrolled', 2)
        ->assertJsonPath('student_impact.clear_count', 1)
        ->assertJsonPath('student_impact.clashing_count', 1)
        ->assertJsonPath('student_impact.clashing_students.0.student_id', $clashingStudent->id)
        ->assertJsonPath('student_impact.clashing_students.0.conflicting_subject', 'PHY-101')
        ->assertJsonPath('student_impact.clashing_students.0.conflicting_section', 'C');
});

it('saves draft schedule without notifications and updates schedule_revision on publish', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $course = Course::factory()->create();
    $room = makeWorkspaceRoom('Room-DRAFT-101', 35);
    $subject = Subject::factory()->create(['code' => 'CS-DRAFT', 'title' => 'Draft Schedule Course']);

    $class = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $room->id,
        'school_year' => $period->getCurrentSchoolYearString(),
        'semester' => $period->getCurrentSemester(),
        'classification' => 'college',
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'notify_students_on_schedule_changes' => true,
        ]),
    ]);

    $student = makeWorkspaceStudent($course, ['email' => 'draft-notify-test@example.com']);
    makeCleanEnrollment($class, $student);

    $initialRevision = app(SchedulingService::class)->revision($class);

    // 1. Save as draft with notify_enrolled = false
    $draftResponse = $this->patchJson(route('administrators.classes.workspace.schedule.update', $class), [
        'schedule_revision' => $initialRevision,
        'schedule_status' => 'draft',
        'notify_enrolled' => false,
        'schedules' => [
            [
                'day_of_week' => 'Friday',
                'start_time' => '08:00',
                'end_time' => '09:30',
                'room_id' => $room->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('schedule_summary.schedule_health.status', 'draft')
        ->assertJsonPath('schedule_summary.schedules.0.status', 'draft');

    $draftRevision = (string) $draftResponse->json('schedule_revision');
    expect($draftRevision)->not->toBe($initialRevision);
    expect(ClassScheduleChange::query()->where('class_id', $class->id)->count())->toBe(0);
    Notification::assertNothingSent();

    // 2. Save as published with notify_enrolled = true -> updates schedule_revision and records/dispatches notification
    $publishResponse = $this->patchJson(route('administrators.classes.workspace.schedule.update', $class), [
        'schedule_revision' => $draftRevision,
        'schedule_status' => 'published',
        'notify_enrolled' => true,
        'schedules' => [
            [
                'day_of_week' => 'Friday',
                'start_time' => '10:00',
                'end_time' => '11:30',
                'room_id' => $room->id,
                'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => 'lecture',
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('schedule_summary.schedule_health.status', 'published')
        ->assertJsonPath('schedule_summary.schedules.0.status', 'published');

    $publishedRevision = (string) $publishResponse->json('schedule_revision');
    expect($publishedRevision)->not->toBe($draftRevision);
    expect(ClassScheduleChange::query()->where('class_id', $class->id)->count())->toBe(1);
    Notification::assertSentOnDemand(ClassScheduleChangedNotification::class);
});

// =========================================================================
// 8. Parallel sections workspace (GET /administrators/classes/{class}/parallel-sections)
// =========================================================================

it('returns parallel sections with merge candidate evaluation and inspected student timetable', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = $period->getCurrentSemester();
    $course = Course::factory()->create();

    $subject = Subject::factory()->create(['code' => 'CS-PAR101', 'title' => 'Parallel Sections Course']);
    $otherSubject = Subject::factory()->create(['code' => 'HIST-101', 'title' => 'World History']);

    $roomA = makeWorkspaceRoom('Room-PAR-A', 30);
    $roomB = makeWorkspaceRoom('Room-PAR-B', 30);
    $roomOther = makeWorkspaceRoom('Room-PAR-C', 30);

    // Section A: 1/20 = 5% (< 35% threshold -> eligible merge candidate into Section B)
    $sectionA = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'A',
        'room_id' => $roomA->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 20,
    ]);

    Schedule::query()->create([
        'class_id' => $sectionA->id,
        'room_id' => $roomA->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    // Section B: 8/20 = 40% (>= 35%, has 12 available slots -> merge target for Section A)
    $sectionB = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => $subject->code,
        'section' => 'B',
        'room_id' => $roomB->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 20,
    ]);

    Schedule::query()->create([
        'class_id' => $sectionB->id,
        'room_id' => $roomB->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '13:00:00',
        'end_time' => '14:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    // Another class that clashes with Section B on Tuesday 13:30-15:00 for an inspected student
    $otherClass = Classes::factory()->create([
        'subject_id' => $otherSubject->id,
        'subject_code' => $otherSubject->code,
        'section' => 'A',
        'room_id' => $roomOther->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 30,
    ]);

    Schedule::query()->create([
        'class_id' => $otherClass->id,
        'room_id' => $roomOther->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '13:30:00',
        'end_time' => '15:00:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'published',
    ]);

    // 1 un-clashing student in Section A (so Section A can cleanly merge into Section B)
    $studentInA = makeWorkspaceStudent($course);
    makeCleanEnrollment($sectionA, $studentInA);

    // 8 students in Section B (40% fill -> not underfilled itself, 12 slots headroom)
    for ($i = 0; $i < 8; $i++) {
        makeCleanEnrollment($sectionB, makeWorkspaceStudent($course));
    }

    // Inspected student enrolled in HIST-101 (clashes with Section B, clear for Section A)
    $inspectedStudent = makeWorkspaceStudent($course);
    makeCleanEnrollment($otherClass, $inspectedStudent);

    $response = $this->getJson(route('administrators.classes.workspace.parallel-sections', [
        'class' => $sectionA->id,
        'inspect_student_id' => $inspectedStudent->id,
    ]))
        ->assertSuccessful()
        ->assertJsonPath('subject_code', 'CS-PAR101')
        ->assertJsonPath('total_enrolled', 9)
        ->assertJsonPath('total_capacity', 40)
        ->assertJsonPath('inspected_student_timetable.student_id', $inspectedStudent->id)
        ->assertJsonPath("inspected_student_timetable.section_checks.{$sectionA->id}.status", 'clear')
        ->assertJsonPath("inspected_student_timetable.section_checks.{$sectionB->id}.status", 'clash');

    $sections = collect($response->json('sections'))->keyBy('id');
    expect($sections)->toHaveCount(2);

    expect($sections->get($sectionA->id)['merge_candidate'])->toMatchArray([
        'is_candidate' => true,
        'target_section_id' => $sectionB->id,
        'target_section' => 'B',
        'clashing_students_count' => 0,
    ]);

    expect($sections->get($sectionB->id)['merge_candidate'])->toBeNull();
    expect($response->json('inspected_student_timetable.blocks'))->toHaveCount(1);
    expect($response->json('inspected_student_timetable.blocks.0.subject_code'))->toBe('HIST-101');
});

// =========================================================================
// 9. Multi-class bulk action (POST /administrators/classes/bulk-action)
// =========================================================================

it('executes assign_faculty, set_capacity, publish_drafts, and enroll_cohort bulk actions across multiple classes', function (): void {
    Notification::fake();

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $period = app(GeneralSettingsService::class);
    $schoolYear = $period->getCurrentSchoolYearString();
    $semester = $period->getCurrentSemester();
    $course = Course::factory()->create();

    $subject1 = Subject::factory()->create(['code' => 'BULK-101', 'title' => 'Bulk One']);
    $subject2 = Subject::factory()->create(['code' => 'BULK-102', 'title' => 'Bulk Two']);
    $newFaculty = Faculty::factory()->create();

    $room1 = makeWorkspaceRoom('Room-BULK-1', 40);
    $room2 = makeWorkspaceRoom('Room-BULK-2', 40);

    $class1 = Classes::factory()->create([
        'subject_id' => $subject1->id,
        'subject_code' => $subject1->code,
        'section' => 'A',
        'room_id' => $room1->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 25,
    ]);

    $class2 = Classes::factory()->create([
        'subject_id' => $subject2->id,
        'subject_code' => $subject2->code,
        'section' => 'A',
        'room_id' => $room2->id,
        'school_year' => $schoolYear,
        'semester' => $semester,
        'classification' => 'college',
        'maximum_slots' => 25,
    ]);

    // Create non-overlapping draft schedules on both classes
    $draftSchedule1 = Schedule::query()->create([
        'class_id' => $class1->id,
        'room_id' => $room1->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00:00',
        'end_time' => '09:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'draft',
    ]);

    $draftSchedule2 = Schedule::query()->create([
        'class_id' => $class2->id,
        'room_id' => $room2->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '10:00:00',
        'end_time' => '11:30:00',
        'delivery_mode' => Classes::DELIVERY_FACE_TO_FACE,
        'status' => 'draft',
    ]);

    $classIds = [$class1->id, $class2->id];

    // 1. assign_faculty
    $this->postJson(route('administrators.classes.bulk-action'), [
        'class_ids' => $classIds,
        'action' => 'assign_faculty',
        'faculty_id' => $newFaculty->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('action', 'assign_faculty')
        ->assertJsonPath('updated_count', 2)
        ->assertJsonPath('skipped_count', 0);

    expect((string) $class1->fresh()->faculty_id)->toBe((string) $newFaculty->id);
    expect((string) $class2->fresh()->faculty_id)->toBe((string) $newFaculty->id);

    // 2. set_capacity
    $this->postJson(route('administrators.classes.bulk-action'), [
        'class_ids' => $classIds,
        'action' => 'set_capacity',
        'maximum_slots' => 45,
    ])
        ->assertSuccessful()
        ->assertJsonPath('action', 'set_capacity')
        ->assertJsonPath('updated_count', 2);

    expect((int) $class1->fresh()->maximum_slots)->toBe(45);
    expect((int) $class2->fresh()->maximum_slots)->toBe(45);

    // 3. publish_drafts
    $this->postJson(route('administrators.classes.bulk-action'), [
        'class_ids' => $classIds,
        'action' => 'publish_drafts',
        'notify_enrolled' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('action', 'publish_drafts')
        ->assertJsonPath('updated_count', 2)
        ->assertJsonPath('skipped_count', 0);

    expect($draftSchedule1->fresh()->status)->toBe('published');
    expect($draftSchedule2->fresh()->status)->toBe('published');

    // 4. enroll_cohort
    $cohortStudent1 = makeWorkspaceStudent($course);
    $cohortStudent2 = makeWorkspaceStudent($course);

    $this->postJson(route('administrators.classes.bulk-action'), [
        'class_ids' => $classIds,
        'action' => 'enroll_cohort',
        'student_identifiers' => [$cohortStudent1->id, $cohortStudent2->id],
    ])
        ->assertSuccessful()
        ->assertJsonPath('action', 'enroll_cohort')
        ->assertJsonPath('updated_count', 2)
        ->assertJsonPath('enrolled_total', 4)
        ->assertJsonPath('errors', []);

    expect(ClassEnrollment::query()->where('class_id', $class1->id)->where('status', true)->count())->toBe(2);
    expect(ClassEnrollment::query()->where('class_id', $class2->id)->where('status', true)->count())->toBe(2);
});
