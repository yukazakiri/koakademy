<?php

declare(strict_types=1);

use App\Enums\ScholarshipType;
use App\Enums\UserRole;
use App\Models\AdditionalFee;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentDiscount;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['activitylog.enabled' => false]);
});

it('preserves separate-transaction receipted additional fees while reconciling editable fees on update', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 100, 'lab_per_unit' => 200, 'miscelaneous' => 3500]);
    $student = Student::factory()->create([
        'id' => 910101,
        'course_id' => $course->id,
        'academic_year' => 2,
        'scholarship_type' => ScholarshipType::Institutional,
    ]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'CS201', 'lecture' => 3, 'laboratory' => 0]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 2,
        'status' => 'Verified By Dept Head',
    ]);

    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'academic_year' => 2,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 300,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
    ]);

    $protectedFee = AdditionalFee::query()->create([
        'enrollment_id' => $enrollment->id,
        'fee_name' => 'Lab Kit Receipted',
        'amount' => 850.00,
        'is_separate_transaction' => true,
        'transaction_number' => 'OR-9821',
    ]);

    $editableFee = AdditionalFee::query()->create([
        'enrollment_id' => $enrollment->id,
        'fee_name' => 'ID Fee',
        'amount' => 150.00,
        'is_separate_transaction' => false,
    ]);

    StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 2,
        'total_tuition' => 300,
        'total_lectures' => 300,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'overall_tuition' => 4800,
        'total_balance' => 4800,
        'discount' => 0,
        'downpayment' => 0,
        'status' => 'unpaid',
    ]);

    // Submit update omitting the protected fee and modifying the editable fee
    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            'student_id' => $student->id,
            'semester' => 1,
            'academic_year' => 2,
            'remarks' => 'Updated by registrar',
            'subjects' => [[
                'subject_id' => $subject->id,
                'class_id' => null,
                'is_modular' => false,
                'exclude_from_tuition' => false,
                'lecture_fee' => 300,
                'laboratory_fee' => 0,
                'enrolled_lecture_units' => 3,
                'enrolled_laboratory_units' => 0,
            ]],
            'discount' => 0,
            'miscellaneous_fee' => 3500,
            'downpayment' => 0,
            'additional_fees' => [
                ['id' => $editableFee->id, 'fee_name' => 'Updated ID Fee', 'amount' => 200],
            ],
        ])
        ->assertRedirect();

    $protectedFee->refresh();
    $editableFee->refresh();
    $enrollment->refresh();

    expect($protectedFee->exists)->toBeTrue()
        ->and($protectedFee->is_separate_transaction)->toBeTrue()
        ->and($protectedFee->transaction_number)->toBe('OR-9821')
        ->and((float) $protectedFee->amount)->toBe(850.0)
        ->and($editableFee->fee_name)->toBe('Updated ID Fee')
        ->and((float) $editableFee->amount)->toBe(200.0)
        ->and($enrollment->remarks)->toBe('Updated by registrar')
        ->and((float) $enrollment->studentTuition->overall_tuition)->toBe(4850.0);
});

it('rejects duplicate subject ids and blocks unauthorized roles from editing enrollments', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 100, 'lab_per_unit' => 0, 'miscelaneous' => 3500]);
    $student = Student::factory()->create(['id' => 910102, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'CS101', 'lecture' => 3, 'laboratory' => 0]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $this->actingAs($librarian)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/edit"))
        ->assertForbidden();

    $this->actingAs($librarian)
        ->get(portalUrlForAdministrators('/administrators/enrollments/create'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            'student_id' => $student->id,
            'semester' => 1,
            'academic_year' => 1,
            'subjects' => [
                [
                    'subject_id' => $subject->id,
                    'enrolled_lecture_units' => 3,
                    'enrolled_laboratory_units' => 0,
                ],
                [
                    'subject_id' => $subject->id,
                    'enrolled_lecture_units' => 3,
                    'enrolled_laboratory_units' => 0,
                ],
            ],
        ])
        ->assertSessionHasErrors('subjects.0.subject_id');
});

it('prevents non-finance roles from tampering with client fees or discounts on update', function (): void {
    $deptHead = User::factory()->create(['role' => UserRole::DepartmentHead]);
    $course = Course::factory()->create(['lec_per_unit' => 250, 'lab_per_unit' => 0, 'miscelaneous' => 3500]);
    $student = Student::factory()->create(['id' => 910103, 'course_id' => $course->id, 'academic_year' => 1]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT101', 'lecture' => 3, 'laboratory' => 0]);

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
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 750,
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
        'total_tuition' => 750,
        'total_lectures' => 750,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'overall_tuition' => 4250,
        'total_balance' => 4250,
        'discount' => 0,
        'downpayment' => 1000,
        'status' => 'unpaid',
    ]);

    $discount50 = EnrollmentDiscount::query()->where('percentage', 50)->first();

    $this->actingAs($deptHead)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            'student_id' => $student->id,
            'semester' => 1,
            'academic_year' => 1,
            'subjects' => [[
                'subject_id' => $subject->id,
                'is_modular' => false,
                'exclude_from_tuition' => true,
                'lecture_fee' => 1,
                'laboratory_fee' => 0,
                'enrolled_lecture_units' => 3,
                'enrolled_laboratory_units' => 0,
            ]],
            'discount' => 50,
            'discount_id' => $discount50?->id,
            'miscellaneous_fee' => 10,
            'downpayment' => 0,
        ])
        ->assertRedirect();

    $tuition = $enrollment->studentTuition()->firstOrFail();
    $se = $enrollment->subjectsEnrolled()->firstOrFail();

    expect((float) $se->lecture_fee)->toBe(750.0)
        ->and((bool) $se->exclude_from_tuition)->toBeFalse()
        ->and((int) $tuition->discount)->toBe(0)
        ->and((float) $tuition->total_miscelaneous_fees)->toBe(3500.0)
        ->and((float) $tuition->overall_tuition)->toBe(4250.0);
});

it('blocks removing graded subjects without confirmation and syncs section on class change', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 100, 'lab_per_unit' => 0, 'miscelaneous' => 3500]);
    $student = Student::factory()->create(['id' => 910104, 'course_id' => $course->id, 'academic_year' => 1]);
    $gradedSub = Subject::factory()->create(['course_id' => $course->id, 'code' => 'MATH101', 'lecture' => 3, 'laboratory' => 0]);
    $otherSub = Subject::factory()->create(['course_id' => $course->id, 'code' => 'ENG101', 'lecture' => 3, 'laboratory' => 0]);
    $classB = Classes::factory()->create([
        'subject_code' => 'ENG101',
        'subject_ids' => [$otherSub->id],
        'course_codes' => [$course->id],
        'section' => 'BSIT-1B',
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
    ]);

    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $gradedSub->id,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 300,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
        'grade' => 1.75,
    ]);

    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $otherSub->id,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'lecture_fee' => 300,
        'laboratory_fee' => 0,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
    ]);

    // Attempting to drop MATH101 without confirm_grade_loss fails
    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            'student_id' => $student->id,
            'semester' => 1,
            'academic_year' => 1,
            'subjects' => [[
                'subject_id' => $otherSub->id,
                'class_id' => $classB->id,
                'enrolled_lecture_units' => 3,
                'enrolled_laboratory_units' => 0,
            ]],
        ])
        ->assertSessionHas('flash.error');

    expect(SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->count())->toBe(2);

    // With confirm_grade_loss=true, it succeeds and syncs section on ENG101
    $this->actingAs($admin)
        ->put(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"), [
            'student_id' => $student->id,
            'semester' => 1,
            'academic_year' => 1,
            'confirm_grade_loss' => true,
            'subjects' => [[
                'subject_id' => $otherSub->id,
                'class_id' => $classB->id,
                'enrolled_lecture_units' => 3,
                'enrolled_laboratory_units' => 0,
            ]],
        ])
        ->assertRedirect();

    $remaining = SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->firstOrFail();
    expect($remaining->subject_id)->toBe($otherSub->id)
        ->and($remaining->class_id)->toBe($classB->id)
        ->and($remaining->section)->toBe('BSIT-1B')
        ->and(ClassEnrollment::query()->where('student_id', $student->id)->where('class_id', $classB->id)->exists())->toBeTrue();
});

it('scopes searchSubjects and getSubjectSections to explicit school_year and semester query params', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-08-01',
        'school_ending_date' => '2027-05-31',
        'semester' => 2,
    ]);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSCS']);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'CS301', 'lecture' => 3, 'laboratory' => 0]);

    Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'CS301',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'CS-3A',
        'school_year' => '2024 - 2025',
        'semester' => 1,
        'maximum_slots' => 30,
    ]);

    $subjectsResponse = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/api/subjects?course_id={$course->id}&school_year=2024%20-%202025&semester=1"))
        ->assertOk();

    expect($subjectsResponse->json('0.has_classes'))->toBeTrue();

    $sectionsResponse = $this->actingAs($admin)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/api/sections?subject_id={$subject->id}&course_id={$course->id}&school_year=2024%20-%202025&semester=1"))
        ->assertOk();

    expect($sectionsResponse->json())->toHaveCount(1)
        ->and($sectionsResponse->json('0.section'))->toBe('CS-3A');
});
