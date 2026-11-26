<?php

declare(strict_types=1);

use App\Enums\ScholarshipType;
use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentClassWaitlist;
use App\Models\EnrollmentPeriod;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentHold;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('enforces add-drop window and blocking add-drop holds on enrollment edit with audited overrides', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 100, 'lab_per_unit' => 0, 'miscelaneous' => 3500]);
    $student = Student::factory()->create(['id' => 920101, 'course_id' => $course->id, 'academic_year' => 1]);
    $sub1 = Subject::factory()->create(['course_id' => $course->id, 'code' => 'CS101', 'lecture' => 3, 'laboratory' => 0]);
    $sub2 = Subject::factory()->create(['course_id' => $course->id, 'code' => 'CS102', 'lecture' => 3, 'laboratory' => 0]);

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
        'subject_id' => $sub1->id,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 300,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
    ]);

    EnrollmentPeriod::query()->create([
        'school_id' => $enrollment->school_id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'regular_starts_at' => now()->subDays(30),
        'regular_ends_at' => now()->subDays(10),
        'add_drop_deadline_at' => now()->subDays(2),
        'enforce_windows' => true,
        'is_manually_closed' => false,
    ]);

    $hold = StudentHold::query()->create([
        'school_id' => $enrollment->school_id,
        'student_id' => $student->id,
        'office' => 'cashier',
        'hold_type' => 'financial',
        'severity' => 'blocking',
        'title' => 'Unpaid balance',
        'reason' => 'Balance due before load changes',
        'blocks_enrollment' => true,
        'blocks_add_drop' => true,
        'status' => 'active',
        'placed_by' => $admin->id,
        'placed_at' => now()->subDay(),
    ]);

    $payload = [
        'student_id' => $student->id,
        'semester' => 1,
        'academic_year' => 1,
        'subjects' => [
            ['subject_id' => $sub1->id, 'enrolled_lecture_units' => 3, 'enrolled_laboratory_units' => 0],
            ['subject_id' => $sub2->id, 'enrolled_lecture_units' => 3, 'enrolled_laboratory_units' => 0],
        ],
    ];

    // Blocked by closed add/drop window first
    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), $payload)
        ->assertSessionHas('flash.error');

    // Window overridden, still blocked by active add/drop hold
    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            ...$payload,
            'override_window' => true,
        ])
        ->assertSessionHas('flash.error');

    // Both overridden with audited reason -> succeeds and marks hold overridden
    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            ...$payload,
            'override_window' => true,
            'override_holds' => true,
            'override_holds_reason' => 'Approved by Dean memo #2026-14',
            'change_reason' => 'Late elective addition',
        ])
        ->assertRedirect();

    $hold->refresh();
    expect($hold->status)->toBe('overridden')
        ->and($hold->override_reason)->toBe('Approved by Dean memo #2026-14')
        ->and(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->count())->toBe(2);
});

it('blocks editing cancelled enrollments and duplicate semester shifts', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['id' => 920102, 'course_id' => $course->id, 'academic_year' => 1]);
    $sub = Subject::factory()->create(['course_id' => $course->id, 'code' => 'GE101', 'lecture' => 3, 'laboratory' => 0]);

    $cancelled = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Cancelled',
    ]);

    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$cancelled->id}"), [
            'student_id' => $student->id,
            'semester' => 1,
            'academic_year' => 1,
            'subjects' => [['subject_id' => $sub->id, 'enrolled_lecture_units' => 3, 'enrolled_laboratory_units' => 0]],
        ])
        ->assertSessionHas('flash.error');

    $sem1 = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Enrolled',
    ]);
    StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 2,
        'academic_year' => 1,
        'status' => 'Pending',
    ]);

    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$sem1->id}"), [
            'student_id' => $student->id,
            'semester' => 2,
            'academic_year' => 1,
            'subjects' => [['subject_id' => $sub->id, 'enrolled_lecture_units' => 3, 'enrolled_laboratory_units' => 0]],
        ])
        ->assertSessionHas('flash.error');
});

it('auto-promotes assigned waitlists, cancels dropped waitlists, and persists structured edit audit trail', function (): void {
    config(['activitylog.enabled' => true]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 100, 'lab_per_unit' => 0, 'miscelaneous' => 3500]);
    $student = Student::factory()->create(['id' => 920103, 'course_id' => $course->id, 'academic_year' => 1]);
    $subKeep = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT101', 'lecture' => 3, 'laboratory' => 0]);
    $subDrop = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT102', 'lecture' => 3, 'laboratory' => 0]);

    $classA = Classes::factory()->create([
        'subject_id' => $subKeep->id,
        'subject_code' => 'IT101',
        'subject_ids' => [$subKeep->id],
        'course_codes' => [$course->id],
        'section' => 'IT-1A',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 40,
    ]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Enrolled',
    ]);

    $seKeep = SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subKeep->id,
        'class_id' => null,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 300,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
    ]);

    $seDrop = SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subDrop->id,
        'class_id' => null,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 300,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
    ]);

    StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 600,
        'total_lectures' => 600,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'overall_tuition' => 4100,
        'total_balance' => 4100,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'unpaid',
    ]);

    $waitKeep = EnrollmentClassWaitlist::query()->create([
        'school_id' => $enrollment->school_id,
        'student_enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_enrollment_id' => $seKeep->id,
        'subject_id' => $subKeep->id,
        'preferred_class_id' => $classA->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'position' => 1,
        'status' => 'waiting',
    ]);

    $waitDrop = EnrollmentClassWaitlist::query()->create([
        'school_id' => $enrollment->school_id,
        'student_enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_enrollment_id' => $seDrop->id,
        'subject_id' => $subDrop->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'position' => 1,
        'status' => 'waiting',
    ]);

    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            'student_id' => $student->id,
            'semester' => 1,
            'academic_year' => 1,
            'notify_student' => false,
            'change_reason' => 'Assigned IT-1A and dropped conflicting elective',
            'subjects' => [[
                'subject_id' => $subKeep->id,
                'class_id' => $classA->id,
                'enrolled_lecture_units' => 3,
                'enrolled_laboratory_units' => 0,
            ]],
        ])
        ->assertRedirect();

    $waitKeep->refresh();
    $waitDrop->refresh();

    $audit = Activity::query()
        ->where('subject_type', StudentEnrollment::class)
        ->where('subject_id', $enrollment->id)
        ->where('event', 'enrollment_edited')
        ->latest('id')
        ->first();

    expect($waitKeep->status)->toBe('promoted')
        ->and($waitKeep->promoted_class_id)->toBe($classA->id)
        ->and($waitDrop->status)->toBe('cancelled')
        ->and($audit)->not->toBeNull()
        ->and($audit->description)->toContain('Assigned IT-1A and dropped conflicting elective')
        ->and($audit->getProperty('change_reason'))->toBe('Assigned IT-1A and dropped conflicting elective')
        ->and($audit->getProperty('removed_subjects'))->toContain('IT102');
});

it('enriches searchSubjects with checklist progress and getStudentDetails with duplicate detection and scholarship', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'semester' => 1,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSIT']);
    $student = Student::factory()->create([
        'id' => 920104,
        'course_id' => $course->id,
        'academic_year' => 1,
        'scholarship_type' => ScholarshipType::TES,
    ]);
    $passedSub = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT101',
        'academic_year' => 1,
        'semester' => 1,
        'lecture' => 3,
        'laboratory' => 0,
    ]);
    $lockedSub = Subject::factory()->create([
        'course_id' => $course->id,
        'code' => 'IT201',
        'academic_year' => 2,
        'semester' => 1,
        'pre_riquisite' => ['IT199'],
        'lecture' => 3,
        'laboratory' => 0,
    ]);

    $prior = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Completed',
    ]);
    SubjectEnrollment::query()->create([
        'enrollment_id' => $prior->id,
        'student_id' => $student->id,
        'subject_id' => $passedSub->id,
        'academic_year' => 1,
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'grade' => 90,
        'grade_outcome' => 'pass',
    ]);

    $current = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Enrolled',
    ]);

    $details = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/api/student-details?student_id={$student->id}&school_year=2025%20-%202026&semester=1"))
        ->assertOk();

    expect($details->json('scholarship_type'))->toBe(ScholarshipType::TES->value)
        ->and($details->json('existing_enrollment.id'))->toBe($current->id)
        ->and($details->json('recommended_unit_cap'))->toBe(24);

    $subjects = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/api/subjects?course_id={$course->id}&student_id={$student->id}&school_year=2025%20-%202026&semester=1"))
        ->assertOk()
        ->json();

    $byCode = collect($subjects)->keyBy('code');
    expect($byCode->get('IT101')['already_passed'])->toBeTrue()
        ->and($byCode->get('IT201')['prerequisite_satisfied'])->toBeFalse();
});
