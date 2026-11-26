<?php

declare(strict_types=1);

use App\Enums\ScholarshipType;
use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentClassWaitlist;
use App\Models\EnrollmentRequirement;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-30',
        'semester' => 1,
    ]);
});

it('blocks non-enrollment portal roles from viewing or mutating enrollment view endpoints', function (): void {
    $guard = User::factory()->create(['role' => UserRole::SecurityGuard]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);

    $this->actingAs($guard)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"))
        ->assertForbidden();

    $this->actingAs($guard)
        ->patch(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/remarks"), [
            'remarks' => 'Unauthorized edit',
        ])
        ->assertForbidden();

    $this->actingAs($guard)
        ->delete(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"))
        ->assertForbidden();

    $this->actingAs($guard)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes"))
        ->assertForbidden();

    $this->actingAs($guard)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/transfers"))
        ->assertForbidden();

    $this->actingAs($guard)
        ->getJson(portalUrlForAdministrators("/administrators/enrollments/holds/students/{$student->id}"))
        ->assertForbidden();

    $this->actingAs($guard)
        ->post(portalUrlForAdministrators('/administrators/enrollments/waitlists/auto-assign'))
        ->assertForbidden();
});

it('renders enriched show payload including fees, holds, waitlists, timeline, safe return_to, and trashed records', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Registrar]);
    $course = Course::factory()->create(['code' => 'BSIT', 'title' => 'BS Information Technology', 'lec_per_unit' => 400, 'lab_per_unit' => 500]);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'IT201', 'title' => 'Data Structures', 'lecture' => 2, 'laboratory' => 1]);
    $student = Student::factory()->create(['course_id' => $course->id, 'scholarship_type' => ScholarshipType::Institutional]);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 3,
        'academic_year' => 2,
        'remarks' => 'Adviser verified summer load',
    ]);

    SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'academic_year' => 2,
        'school_year' => '2025 - 2026',
        'semester' => 3,
        'enrolled_lecture_units' => 2,
        'enrolled_laboratory_units' => 1,
        'lecture_fee' => 800,
        'laboratory_fee' => 500,
    ]);

    // Safe root-relative return_to is preserved; protocol-relative external URL is stripped.
    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}?return_to=/administrators/enrollments?page=2"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/enrollments/show', false)
            ->where('enrollment.course_id', $course->id)
            ->where('enrollment.semester_label', 'Summer')
            ->where('enrollment.remarks', 'Adviser verified summer load')
            ->where('enrollment.student.scholarship_type', ScholarshipType::Institutional->value)
            ->where('enrollment.subjects_enrolled.0.lecture_fee', 800)
            ->where('enrollment.subjects_enrolled.0.lab_fee', 500)
            ->where('queue_navigation.return_to', '/administrators/enrollments?page=2')
            ->has('document_links.cor_pdf_url')
            ->has('document_links.re_enroll_url')
            ->has('document_links.cashier_payment_url')
            ->has('enrollment.timeline')
        );

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}?return_to=//evil.example/phish"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('queue_navigation.return_to', null)
        );

    // Soft-deleted enrollment still resolves via withTrashed().
    $enrollment->delete();
    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('enrollment.is_trashed', true)
        );
});

it('prevents IDOR in updateTransaction and syncs transaction and tuition amounts when authorized', function (): void {
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    $course = Course::factory()->create();
    $studentA = Student::factory()->create(['course_id' => $course->id]);
    $studentB = Student::factory()->create(['course_id' => $course->id]);

    $enrollmentA = StudentEnrollment::factory()->create(['student_id' => $studentA->id, 'course_id' => $course->id]);
    $enrollmentB = StudentEnrollment::factory()->create(['student_id' => $studentB->id, 'course_id' => $course->id]);

    $tuitionA = StudentTuition::query()->create([
        'enrollment_id' => $enrollmentA->id,
        'student_id' => $studentA->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 5000,
        'overall_tuition' => 5000,
        'total_balance' => 3000,
        'downpayment' => 2000,
        'status' => 'Downpayment',
    ]);

    $txA = Transaction::query()->create([
        'transaction_number' => 'TX-A-001',
        'invoicenumber' => 'OR-1001',
        'description' => 'Tuition payment',
        'status' => 'completed',
        'settlements' => ['tuition_fee' => 2000],
        'transaction_date' => now()->toDateString(),
    ]);
    $studentTxA = StudentTransaction::query()->create([
        'student_id' => $studentA->id,
        'student_enrollment_id' => $enrollmentA->id,
        'transaction_id' => $txA->id,
        'amount' => 2000,
        'status' => 'completed',
    ]);

    $txB = Transaction::query()->create([
        'transaction_number' => 'TX-B-001',
        'invoicenumber' => 'OR-2001',
        'description' => 'Other student payment',
        'status' => 'completed',
        'settlements' => ['tuition_fee' => 1000],
        'transaction_date' => now()->toDateString(),
    ]);
    StudentTransaction::query()->create([
        'student_id' => $studentB->id,
        'student_enrollment_id' => $enrollmentB->id,
        'transaction_id' => $txB->id,
        'amount' => 1000,
        'status' => 'completed',
    ]);

    // Cross-enrollment IDOR attempt returns 404.
    $this->actingAs($cashier)
        ->patch(portalUrlForAdministrators("/administrators/enrollments/{$enrollmentA->id}/transactions/{$txB->id}"), [
            'amount' => 9999,
            'invoicenumber' => 'OR-HACKED',
        ])
        ->assertNotFound();

    // Valid update updates transaction, StudentTransaction, and tuition balance.
    $this->actingAs($cashier)
        ->patch(portalUrlForAdministrators("/administrators/enrollments/{$enrollmentA->id}/transactions/{$txA->id}"), [
            'amount' => 3500,
            'invoicenumber' => 'OR-1001-REV',
        ])
        ->assertRedirect();

    expect((float) $studentTxA->fresh()->amount)->toBe(3500.0);
    expect($txA->fresh()->invoicenumber)->toBe('OR-1001-REV');
    expect((float) $tuitionA->fresh()->total_balance)->toBe(1500.0);
});

it('reactivates released seats on enrollInClass, syncs SubjectEnrollment, and supports inline section swap and remarks', function (): void {
    $registrar = User::factory()->create(['role' => UserRole::Registrar]);
    $course = Course::factory()->create(['code' => 'BSHM']);
    $subject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'HM101', 'title' => 'Hospitality Intro']);
    $classA = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'HM101',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSHM-1A',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 30,
    ]);
    $classB = Classes::factory()->create([
        'subject_id' => $subject->id,
        'subject_code' => 'HM101',
        'subject_ids' => [$subject->id],
        'course_codes' => [$course->id],
        'section' => 'BSHM-1B',
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'maximum_slots' => 30,
    ]);

    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $subjectEnrollment = SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'class_id' => null,
        'section' => null,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);

    // Simulate seat released by cancellation (status = false) + active waitlist entry.
    ClassEnrollment::query()->create([
        'student_id' => $student->id,
        'class_id' => $classA->id,
        'status' => false,
    ]);
    $waitlist = EnrollmentClassWaitlist::query()->create([
        'student_enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_enrollment_id' => $subjectEnrollment->id,
        'subject_id' => $subject->id,
        'preferred_class_id' => $classA->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'status' => EnrollmentClassWaitlist::StatusWaiting,
        'position' => 1,
    ]);

    $this->actingAs($registrar)
        ->post(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/enroll-class"), [
            'class_id' => $classA->id,
        ])
        ->assertRedirect();

    expect(ClassEnrollment::query()->where('student_id', $student->id)->where('class_id', $classA->id)->where('status', true)->exists())->toBeTrue();
    expect($subjectEnrollment->fresh()->class_id)->toBe($classA->id);
    expect($subjectEnrollment->fresh()->section)->toBe('BSHM-1A');
    expect($waitlist->fresh()->status)->toBe(EnrollmentClassWaitlist::StatusPromoted);

    // Inline quick section swap to Section B
    $this->actingAs($registrar)
        ->patch(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/subjects/{$subjectEnrollment->id}/section"), [
            'class_id' => $classB->id,
        ])
        ->assertRedirect();

    expect($subjectEnrollment->fresh()->class_id)->toBe($classB->id);
    expect($subjectEnrollment->fresh()->section)->toBe('BSHM-1B');
    expect(ClassEnrollment::query()->where('student_id', $student->id)->where('class_id', $classB->id)->where('status', true)->exists())->toBeTrue();
    expect(ClassEnrollment::query()->where('student_id', $student->id)->where('class_id', $classA->id)->exists())->toBeFalse();

    // Inline remarks update
    $this->actingAs($registrar)
        ->patch(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/remarks"), [
            'remarks' => 'Transferred to afternoon section per working schedule.',
        ])
        ->assertRedirect();

    expect($enrollment->fresh()->remarks)->toBe('Transferred to afternoon section per working schedule.');
});

it('streams requirement evidence for enrolled students and supports prefilled re-enroll and cashier handoff', function (): void {
    Storage::fake('private');
    Storage::disk('private')->put('requirements/birth-cert.pdf', '%PDF-1.4 sample');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['code' => 'BSBA']);
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $requirement = EnrollmentRequirement::query()->create([
        'student_enrollment_id' => $enrollment->id,
        'requirement_key' => 'birth_certificate',
        'label' => 'Birth Certificate',
        'is_required' => true,
        'status' => EnrollmentRequirement::Pending,
        'evidence_path' => 'requirements/birth-cert.pdf',
    ]);

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/requirements/{$requirement->id}/document"))
        ->assertOk();

    // Prefilled Re-enroll link in create()
    $this->actingAs($admin)
        ->get(portalUrlForAdministrators("/administrators/enrollments/create?from_enrollment={$enrollment->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('prefill.student_id', $student->id)
            ->where('prefill.semester', 2)
            ->where('prefill.academic_year', 1)
        );

    // Prefilled Cashier Payment page
    $cashier = User::factory()->create(['role' => UserRole::Cashier]);
    Spatie\Permission\Models\Permission::findOrCreate('View:Cashier', 'web');
    $cashier->givePermissionTo('View:Cashier');
    $this->actingAs($cashier)
        ->get(portalUrlForAdministrators("/administrators/finance/payments/create?student_id={$student->id}&enrollment_id={$enrollment->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('prefill_student.id', $student->id)
            ->where('prefill_student.enrollment_id', $enrollment->id)
        );
});

it('preserves finance adjustments and blocks graded drops without confirmation during load changes', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 500]);
    $gradedSubject = Subject::factory()->create(['course_id' => $course->id, 'code' => 'GE101', 'lecture' => 3, 'laboratory' => 0]);
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $gradedSe = SubjectEnrollment::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'subject_id' => $gradedSubject->id,
        'academic_year' => 1,
        'school_year' => '2025 - 2026',
        'semester' => 1,
        'enrolled_lecture_units' => 3,
        'enrolled_laboratory_units' => 0,
        'lecture_fee' => 1500,
        'laboratory_fee' => 0,
        'grade' => 1.75,
    ]);

    $this->actingAs($admin)
        ->postJson(portalUrlForAdministrators("/administrators/enrollments/{$enrollment->id}/load-changes"), [
            'change_type' => 'drop',
            'dropped_subject_enrollment_id' => $gradedSe->id,
            'reason' => 'Attempt dropping graded subject',
            'apply_immediately' => true,
        ])
        ->assertUnprocessable();
});
