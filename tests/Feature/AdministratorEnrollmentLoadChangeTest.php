<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentLoadChange;
use App\Models\EnrollmentPeriod;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-30',
        'semester' => 1,
    ]);
});

it('previews, swaps a subject, recalculates tuition delta, and renders the printable COL slip', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSIT', 'lec_per_unit' => 500, 'lab_per_unit' => 600]);
    $oldSubject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT101', 'lecture' => 3, 'laboratory' => 0]);
    $newSubject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT102', 'lecture' => 3, 'laboratory' => 1]);
    $newClass = Classes::factory()->create([
        'subject_code' => 'IT102',
        'subject_ids' => [$newSubject->id],
        'course_codes' => [$course->id],
        'section' => 'BSIT-1B',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 40,
    ]);

    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'status' => 'Verified By Cashier',
    ]);

    $oldSubjectEnrollment = SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $oldSubject->id,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
        'lecture_fee' => 1500,
        'laboratory_fee' => 0,
    ]);

    $tuition = StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'total_lectures' => 1500,
        'total_laboratory' => 0,
        'total_tuition' => 1500,
        'total_miscelaneous_fees' => 1000,
        'overall_tuition' => 2500,
        'total_balance' => 1500, // 1000 already paid
        'discount' => 0,
        'downpayment' => 1000,
        'status' => 'partially_paid',
    ]);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes/preview"), [
            'change_type' => 'swap',
            'added_subject_id' => $newSubject->id,
            'dropped_subject_enrollment_id' => $oldSubjectEnrollment->id,
        ])
        ->assertOk()
        ->assertJsonPath('units_delta', 1)
        ->assertJsonPath('tuition_delta', 600)
        ->assertJsonPath('new_tuition_total', 3100);

    $response = $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes"), [
            'change_type' => 'swap',
            'added_subject_id' => $newSubject->id,
            'added_class_id' => $newClass->id,
            'dropped_subject_enrollment_id' => $oldSubjectEnrollment->id,
            'reason' => 'Student swapped to laboratory elective.',
            'apply_immediately' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('load_change.status', 'approved')
        ->assertJsonPath('load_change.tuition_delta', 600);

    $tuition->refresh();
    expect((float) $tuition->overall_tuition)->toBe(3100.0);
    expect((float) $tuition->total_balance)->toBe(2100.0); // Preserved 1000 paid!
    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $newSubject->id)->exists())->toBeTrue();
    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $oldSubject->id)->exists())->toBeFalse();

    $changeId = $response->json('load_change.id');
    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes/{$changeId}/slip"))
        ->assertOk()
        ->assertSee('Official Change of Load')
        ->assertSee('IT101')
        ->assertSee('IT102');
});

it('enforces the add/drop deadline window and supports pending queue approval/rejection', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 600]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'GE101', 'lecture' => 3, 'laboratory' => 0]);
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    EnrollmentPeriod::query()->create([
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'add_drop_deadline_at' => now()->subDays(2),
        'enforce_windows' => true,
    ]);

    // Past deadline without override fails
    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes"), [
            'change_type' => 'add',
            'added_subject_id' => $subject->id,
            'reason' => 'Late subject addition.',
            'apply_immediately' => false,
        ])
        ->assertUnprocessable();

    // With override, queues pending then rejects
    $created = $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes"), [
            'change_type' => 'add',
            'added_subject_id' => $subject->id,
            'reason' => 'Late subject addition with dean approval.',
            'apply_immediately' => false,
            'override_window' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('load_change.status', 'pending');

    $changeId = $created->json('load_change.id');

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes/{$changeId}/reject"), [
            'rejection_reason' => 'Exceeds maximum unit cap.',
        ])
        ->assertOk()
        ->assertJsonPath('load_change.status', 'rejected');

    expect(EnrollmentLoadChange::query()->find($changeId)?->rejection_reason)->toBe('Exceeds maximum unit cap.');
});
