<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentTransferLog;
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

it('executes a tracked block section transfer across matched subjects and logs history', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSIT']);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT101', 'lecture' => 3, 'laboratory' => 0]);

    $classA = Classes::factory()->create([
        'subject_code' => 'IT101',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSIT-1A',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 35,
    ]);
    $classB = Classes::factory()->create([
        'subject_code' => 'IT101',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSIT-1B',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 35,
    ]);

    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'class_id' => $classA->id,
        'section' => 'BSIT-1A',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    ClassEnrollment::query()->create(['student_id' => $student->id, 'class_id' => $classA->id, 'status' => true]);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/transfers/section"), [
            'target_section' => 'BSIT-1B',
            'reason' => 'Working student moved to afternoon block.',
        ])
        ->assertOk()
        ->assertJsonPath('transfer.to_section', 'BSIT-1B')
        ->assertJsonPath('transfer.reassigned_classes_count', 1);

    expect(ClassEnrollment::query()->where('student_id', $student->id)->where('class_id', $classB->id)->exists())->toBeTrue();
    expect(ClassEnrollment::query()->where('student_id', $student->id)->where('class_id', $classA->id)->exists())->toBeFalse();
    expect(EnrollmentTransferLog::query()->where('transfer_type', EnrollmentTransferLog::TypeSectionTransfer)->count())->toBe(1);
});

it('executes a tracked program shift crediting overlapping subjects and reconciling tuition delta', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $bsit = Course::factory()->create(['code' => 'BSIT', 'lec_per_unit' => 500, 'lab_per_unit' => 600, 'miscelaneous' => 1000]);
    $bscs = Course::factory()->create(['code' => 'BSCS', 'lec_per_unit' => 600, 'lab_per_unit' => 700, 'miscelaneous' => 1500]);

    // Overlapping GE subject + BSIT-only subject + BSCS-only subject
    $geIt = Subject::factory()->create(['course_id' => $bsit->id, 'code' => 'GE101', 'title' => 'Purposive Communication', 'academic_year' => 1, 'semester' => 1, 'lecture' => 3, 'laboratory' => 0]);
    $itOnly = Subject::factory()->create(['course_id' => $bsit->id, 'code' => 'IT101', 'title' => 'Intro to IT', 'academic_year' => 1, 'semester' => 1, 'lecture' => 3, 'laboratory' => 0]);

    $geCs = Subject::factory()->create(['course_id' => $bscs->id, 'code' => 'GE101', 'title' => 'Purposive Communication', 'academic_year' => 1, 'semester' => 1, 'lecture' => 3, 'laboratory' => 0]);
    $csOnly = Subject::factory()->create(['course_id' => $bscs->id, 'code' => 'CS101', 'title' => 'Discrete Math', 'academic_year' => 1, 'semester' => 1, 'lecture' => 3, 'laboratory' => 0]);

    $student = Student::factory()->create(['course_id' => $bsit->id, 'academic_year' => 1]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $bsit->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $geIt->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'enrolled_lecture_units' => 3,
        'lecture_fee' => 1500,
    ]);
    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $itOnly->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'enrolled_lecture_units' => 3,
        'lecture_fee' => 1500,
    ]);
    $tuition = StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'total_lectures' => 3000,
        'total_laboratory' => 0,
        'total_tuition' => 3000,
        'total_miscelaneous_fees' => 1000,
        'overall_tuition' => 4000,
        'total_balance' => 3000, // 1000 paid
        'discount' => 0,
        'downpayment' => 1000,
        'status' => 'partially_paid',
    ]);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/transfers/program"), [
            'target_course_id' => $bscs->id,
            'target_year_level' => 1,
            'reconcile_curriculum' => true,
            'reason' => 'Approved shift to BSCS.',
        ])
        ->assertOk()
        ->assertJsonPath('transfer.from_course', 'BSIT')
        ->assertJsonPath('transfer.to_course', 'BSCS')
        ->assertJsonPath('transfer.credited_subjects_count', 1);

    expect($enrollment->fresh()->course_id)->toBe($bscs->id);
    expect($student->fresh()->course_id)->toBe($bscs->id);
    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $geCs->id)->exists())->toBeTrue();
    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $csOnly->id)->exists())->toBeTrue();
    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->where('subject_id', $itOnly->id)->exists())->toBeFalse();

    // New tuition: 6 lec units * 600 = 3600 + 1500 misc = 5100; 1000 already paid => 4100 balance!
    $tuition->refresh();
    expect((float) $tuition->overall_tuition)->toBe(5100.0);
    expect((float) $tuition->total_balance)->toBe(4100.0);
});
