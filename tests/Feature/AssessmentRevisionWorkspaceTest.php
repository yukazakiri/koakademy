<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Finance\RecordFinancePayment;
use App\Models\AssessmentRevision;
use App\Models\Course;
use App\Models\FeeScheduleVersion;
use App\Models\GeneralSetting;
use App\Models\PaymentAllocation;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\AssessmentCalculationService;
use App\Services\EnrollmentBillingService;
use App\Services\StatementOfAccountService;
use App\Services\TuitionAdjustmentRecalculationService;
use App\Services\TuitionAdjustmentService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Modules\Inventory\Models\InventoryProduct;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function assessmentTestUser(string $role = 'Accounting Officer'): User
{
    $enumRole = match ($role) {
        'Cashier' => UserRole::Cashier,
        default => UserRole::AccountingOfficer,
    };
    $user = User::factory()->create(['role' => $enumRole]);
    foreach (['view_tuition_fees', 'manage_tuition_fees', 'View:Cashier'] as $perm) {
        Permission::findOrCreate($perm, 'web');
    }
    $userRole = Role::findOrCreate($user->role->value, 'web');
    $userRole->syncPermissions(['view_tuition_fees', 'manage_tuition_fees', 'View:Cashier']);
    $user->syncRoles([$userRole]);
    $user->givePermissionTo(['view_tuition_fees', 'manage_tuition_fees', 'View:Cashier']);

    return $user;
}

beforeEach(function (): void {
    Notification::fake();
    School::factory()->create();
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

it('calculates discounts on lecture fees only without double discounting upon adjustment', function (): void {
    $calculator = app(AssessmentCalculationService::class);
    $components = $calculator->calculateComponents(
        grossLecture: 10000.00,
        laboratory: 2000.00,
        modular: 0.00,
        miscellaneous: 1000.00,
        additionalFees: 500.00,
        discountPercentage: 20,
    );

    expect($components['gross_lecture'])->toBe(10000.00)
        ->and($components['discount_percentage'])->toBe(20)
        ->and($components['discount_amount'])->toBe(2000.00)
        ->and($components['discounted_lecture'])->toBe(8000.00)
        ->and($components['laboratory'])->toBe(2000.00)
        ->and($components['miscellaneous'])->toBe(1000.00)
        ->and($components['additional_fees'])->toBe(500.00)
        ->and($components['total_tuition'])->toBe(10000.00)
        ->and($components['overall_tuition'])->toBe(11500.00)
        ->and($components['assessment_adjustment'])->toBe(0.00);

    // Apply through TuitionAdjustmentService
    $actor = assessmentTestUser();
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id, 'student_type' => 'college']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 10000.00,
        'total_lectures' => 8000.00,
        'gross_lecture' => 10000.00,
        'total_laboratory' => 2000.00,
        'total_miscelaneous_fees' => 1000.00,
        'overall_tuition' => 11000.00,
        'total_balance' => 11000.00,
        'discount' => 20,
        'downpayment' => 0.00,
        'paid' => 0.00,
    ]);

    $service = app(TuitionAdjustmentService::class);
    $serialized = $service->serialize($enrollment);

    // Adjustment with unchanged components
    $response = $this->actingAs($actor)->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), [
        'batch_key' => (string) Str::uuid(),
        'reason' => 'Ledger reconciliation test',
        'rows' => [[
            'client_row_id' => 'disc-row-1',
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $tuition->id,
            'state_hash' => $serialized['state_hash'],
            'total_fees' => 11000.00,
            'opening_paid' => 0.00,
            'balance' => 11000.00,
            'lecture' => 8000.00,
            'laboratory' => 2000.00,
            'miscellaneous' => 1000.00,
            'discount' => 20,
            'installments' => ['prelim' => 3500.00, 'midterm' => 3500.00, 'finals' => 4000.00],
        ]],
    ]);

    $response->assertSuccessful()->assertJsonPath('rows.0.status', 'recorded');
    $tuition->refresh();
    expect((float) $tuition->overall_tuition)->toBe(11000.00)
        ->and((float) $tuition->total_lectures)->toBe(8000.00)
        ->and((float) $tuition->assessment_adjustment)->toBe(0.00);

    // Revision was recorded
    expect($tuition->assessmentRevisions()->count())->toBe(1)
        ->and($tuition->activeRevision)->not->toBeNull()
        ->and((float) $tuition->activeRevision->gross_lecture)->toBe(10000.00)
        ->and((float) $tuition->activeRevision->discounted_lecture)->toBe(8000.00)
        ->and((float) $tuition->activeRevision->discount_amount)->toBe(2000.00);
});

it('records durable payment allocations for mixed items and multi-semester tuition', function (): void {
    $cashier = assessmentTestUser('Cashier');
    $student = Student::factory()->create();
    $course = Course::factory()->create();

    // Sem 1 enrollment & tuition
    $sem1Enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    $sem1Tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $sem1Enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 10000,
        'total_lectures' => 10000,
        'overall_tuition' => 10000,
        'total_balance' => 10000,
        'paid' => 0,
        'discount' => 0,
    ]);

    // Sem 2 enrollment & tuition
    $sem2Enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 2,
    ]);
    $sem2Tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $sem2Enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 2,
        'academic_year' => 1,
        'total_tuition' => 8000,
        'total_lectures' => 8000,
        'overall_tuition' => 8000,
        'total_balance' => 8000,
        'paid' => 0,
        'discount' => 0,
    ]);

    // Inventory product
    $uniform = InventoryProduct::factory()->create([
        'name' => 'College Uniform',
        'price' => 750.00,
        'stock_quantity' => 5,
        'track_stock' => true,
        'is_active' => true,
    ]);

    $recorder = app(RecordFinancePayment::class);
    $result = $recorder->record($cashier, [
        'student_id' => $student->id,
        'payment_method' => PaymentMethod::Cash->value,
        'reference_number' => 'OR-MIXED-1',
        'remarks' => 'Tuitions and uniform',
        'items' => [
            ['type' => 'tuition', 'tuition_id' => $sem1Tuition->id, 'amount' => 4000.00],
            ['type' => 'tuition', 'tuition_id' => $sem2Tuition->id, 'amount' => 2500.00],
            ['type' => 'fee', 'fee_key' => 'id_replacement', 'amount' => 150.00],
            ['type' => 'item', 'id' => $uniform->id, 'quantity' => 1],
        ],
    ]);

    expect($result->duplicate)->toBeFalse();

    // Check allocations
    $allocations = PaymentAllocation::query()->where('student_id', $student->id)->get();
    expect($allocations)->toHaveCount(4);

    $billing = app(EnrollmentBillingService::class);
    $sem1Paid = $billing->verifiedPaid($sem1Tuition);
    $sem2Paid = $billing->verifiedPaid($sem2Tuition);

    // Sem 1 received exactly 4000, Sem 2 received exactly 2500
    // Inventory ($750) and ID fee ($150) did NOT inflate tuition!
    expect($sem1Paid)->toBe(4000.00)
        ->and($sem2Paid)->toBe(2500.00);

    $sem1Tuition->refresh();
    $sem2Tuition->refresh();
    expect((float) $sem1Tuition->total_balance)->toBe(6000.00)
        ->and((float) $sem2Tuition->total_balance)->toBe(5500.00);
});

it('rejects stale rows when concurrent cashier payment occurs during revision', function (): void {
    $actor = assessmentTestUser();
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 10000,
        'total_lectures' => 10000,
        'overall_tuition' => 10000,
        'total_balance' => 10000,
        'paid' => 0,
        'discount' => 0,
    ]);

    $service = app(TuitionAdjustmentService::class);
    $initialSnapshot = $service->serialize($enrollment);

    // Simulate cashier payment in the background
    $tuition->forceFill(['paid' => 1000, 'total_balance' => 9000])->save();

    // Staff tries to apply with stale state hash
    $response = $this->actingAs($actor)->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), [
        'batch_key' => (string) Str::uuid(),
        'reason' => 'Late adjustment',
        'rows' => [[
            'client_row_id' => 'stale-row-test',
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $tuition->id,
            'state_hash' => $initialSnapshot['state_hash'],
            'total_fees' => 9500,
            'opening_paid' => 0,
            'balance' => 9500,
            'installments' => ['prelim' => 3000, 'midterm' => 3000, 'finals' => 3500],
        ]],
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('rows.0.status', 'rejected')
        ->assertJsonPath('rows.0.message', 'This tuition changed after it was loaded. Refresh the row and try again.');
});

it('freezes approved assessments when academic edits change enrolled subjects', function (): void {
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 1000]);
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 12000,
        'total_lectures' => 10000,
        'total_laboratory' => 2000,
        'overall_tuition' => 12000,
        'total_balance' => 12000,
        'assessment_adjustment' => 500,
        'paid' => 0,
        'discount' => 0,
    ]);

    $revision = AssessmentRevision::query()->create([
        'student_tuition_id' => $tuition->id,
        'student_enrollment_id' => $enrollment->id,
        'overall_tuition' => 12000.0,
        'fingerprint' => 'test-fingerprint',
    ]);
    $tuition->forceFill(['active_revision_id' => $revision->id])->save();

    $recalc = app(TuitionAdjustmentRecalculationService::class);
    // Academic edit recomputes raw overall to 15000 due to added subject
    $preserved = $recalc->preserveFinanceAdjustment($tuition->refresh(), [
        'overall_tuition' => 15000.00,
        'total_tuition' => 15000.00,
        'total_lectures' => 13000.00,
        'total_laboratory' => 2000.00,
        'total_balance' => 15000.00,
    ]);

    // Assessment charges remain frozen at the Finance approved total of 12000!
    expect($preserved['overall_tuition'])->toBe(12000.00)
        ->and($preserved['total_tuition'])->toBe(12000.00)
        ->and($preserved['needs_finance_review'])->toBeTrue();
});

it('keeps legacy assessment adjustments additive without an active revision', function (): void {
    $course = Course::factory()->create(['lec_per_unit' => 500, 'lab_per_unit' => 1000]);
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 12000,
        'total_lectures' => 10000,
        'total_laboratory' => 2000,
        'overall_tuition' => 12000,
        'total_balance' => 12000,
        'assessment_adjustment' => 500,
        'paid' => 0,
        'discount' => 0,
    ]);

    $preserved = app(TuitionAdjustmentRecalculationService::class)->preserveFinanceAdjustment($tuition, [
        'overall_tuition' => 15000.00,
        'total_balance' => 15000.00,
    ]);

    expect($preserved['overall_tuition'])->toBe(15500.00)
        ->and($preserved['total_balance'])->toBe(15500.00)
        ->and($preserved['assessment_adjustment'])->toBe(500.0);
});

it('unifies balance, paid, and credit totals across billing, SOA, and adjustment serialization', function (): void {
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    // Overpaid tuition producing credit
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 8000,
        'total_lectures' => 8000,
        'overall_tuition' => 8000,
        'total_balance' => 0,
        'paid' => 10000,
        'discount' => 0,
    ]);

    $billing = app(EnrollmentBillingService::class);
    $soa = app(StatementOfAccountService::class)->build($student, '2026 - 2027', 1);
    $adj = app(TuitionAdjustmentService::class)->serialize($enrollment);
    $summary = $billing->toSummaryArray($tuition);

    // All three report identical paid (10000), balance (0), and credit (2000)
    expect((float) $summary['total_paid'])->toBe(10000.0)
        ->and((float) $summary['total_balance'])->toBe(0.0)
        ->and((float) $summary['credit'])->toBe(2000.0)
        ->and((float) $soa['tuition']['total_paid'])->toBe(10000.0)
        ->and((float) $soa['tuition']['total_balance'])->toBe(0.0)
        ->and((float) $soa['tuition']['credit'])->toBe(2000.0)
        ->and((float) $adj['paid'])->toBe(10000.0)
        ->and((float) $adj['balance_due'])->toBe(0.0)
        ->and((float) $adj['credit'])->toBe(2000.0);
});

it('previews differences and retrieves revision history via endpoints', function (): void {
    $actor = assessmentTestUser();
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 15000,
        'total_lectures' => 12000,
        'total_laboratory' => 3000,
        'overall_tuition' => 15000,
        'total_balance' => 15000,
        'paid' => 0,
        'discount' => 0,
    ]);

    // Test preview endpoint
    $previewResponse = $this->actingAs($actor)->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/preview'), [
        'rows' => [[
            'client_row_id' => 'preview-row-1',
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $tuition->id,
            'total_fees' => 14000,
            'opening_paid' => 0,
            'balance' => 14000,
            'lecture' => 11000,
            'laboratory' => 3000,
            'miscellaneous' => 0,
            'discount' => 0,
        ]],
        'calculation_method' => 'reconciled_assessment',
    ]);

    $previewResponse->assertOk()
        ->assertJsonPath('rows.0.status', 'ready')
        ->assertJsonPath('rows.0.differences.total_fees_delta', -1000)
        ->assertJsonPath('rows.0.proposed.total_fees', 14000);

    // Apply batch to create revision
    $serialized = app(TuitionAdjustmentService::class)->serialize($enrollment);
    $this->actingAs($actor)->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), [
        'batch_key' => (string) Str::uuid(),
        'reason' => 'Scholarship discount revision',
        'rows' => [[
            'client_row_id' => 'rev-row-1',
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $tuition->id,
            'state_hash' => $serialized['state_hash'],
            'total_fees' => 14000,
            'opening_paid' => 0,
            'balance' => 14000,
            'lecture' => 11000,
            'laboratory' => 3000,
            'miscellaneous' => 0,
            'discount' => 0,
            'installments' => ['prelim' => 4000, 'midterm' => 4000, 'finals' => 6000],
        ]],
    ])->assertSuccessful();

    // Test history endpoint
    $historyResponse = $this->actingAs($actor)->getJson(
        portalUrlForAdministrators("/administrators/finance/tuition-adjustments/revisions/{$tuition->id}")
    );

    $historyResponse->assertOk()
        ->assertJsonCount(1, 'revisions')
        ->assertJsonPath('revisions.0.revision_number', 1)
        ->assertJsonPath('revisions.0.overall_tuition', 14000)
        ->assertJsonPath('revisions.0.reason', 'Scholarship discount revision');
});

it('recalculates assessments from fee schedule versions correctly', function (): void {
    $course = Course::factory()->create();
    $feeSchedule = FeeScheduleVersion::query()->create([
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'version' => 1,
        'lecture_rate_per_unit' => 600.00,
        'laboratory_rate_per_unit' => 1800.00,
        'miscellaneous_fee' => 1200.00,
        'modular_fee' => 2400.00,
        'is_active' => true,
    ]);

    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
    ]);

    $subject1 = Subject::factory()->create(['course_id' => $course->id, 'lecture' => 3, 'laboratory' => 0, 'code' => 'IT101']);
    $subject2 = Subject::factory()->create(['course_id' => $course->id, 'lecture' => 2, 'laboratory' => 1, 'code' => 'IT102']);

    SubjectEnrollment::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $subject1->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026 - 2027',
        'lecture_fee' => 0,
        'laboratory_fee' => 0,
        'exclude_from_tuition' => false,
    ]);
    SubjectEnrollment::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'subject_id' => $subject2->id,
        'semester' => 1,
        'academic_year' => 1,
        'school_year' => '2026 - 2027',
        'lecture_fee' => 0,
        'laboratory_fee' => 0,
        'exclude_from_tuition' => false,
    ]);

    $calc = app(AssessmentCalculationService::class)->calculateFromEnrolledSubjects(
        enrollment: $enrollment,
        feeSchedule: $feeSchedule,
        discountPercentage: 10,
    );

    // Subject 1: (3+0)*600 = 1800 lec
    // Subject 2: (2+1)*600 = 1800 lec, 1800 lab
    // Total gross lecture = 3600. Discount 10% = 360. Net lecture = 3240.
    // Total lab = 1800.
    // Misc = 1200.
    // Total tuition = 3240 + 1800 = 5040. Overall = 5040 + 1200 = 6240.
    expect($calc['gross_lecture'])->toBe(3600.00)
        ->and($calc['discount_amount'])->toBe(360.00)
        ->and($calc['discounted_lecture'])->toBe(3240.00)
        ->and($calc['laboratory'])->toBe(1800.00)
        ->and($calc['miscellaneous'])->toBe(1200.00)
        ->and($calc['total_tuition'])->toBe(5040.00)
        ->and($calc['overall_tuition'])->toBe(6240.00);
});

it('forbids unauthorized users and rejects ambiguous enrollment resolution', function (): void {
    $unauthorized = User::factory()->create(['role' => UserRole::Student]);
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);
    $enrollment = StudentEnrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id]);
    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $enrollment->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'overall_tuition' => 5000,
        'total_balance' => 5000,
        'paid' => 0,
        'discount' => 0,
    ]);

    $this->actingAs($unauthorized)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/preview'), ['rows' => []])
        ->assertForbidden();

    $this->actingAs($unauthorized)
        ->getJson(portalUrlForAdministrators("/administrators/finance/tuition-adjustments/revisions/{$tuition->id}"))
        ->assertForbidden();

    $authorized = assessmentTestUser();
    $resolveResponse = $this->actingAs($authorized)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/resolve'), [
            'school_year' => '2026 - 2027',
            'semester' => 1,
            'rows' => [
                ['client_row_id' => 'row-unmatched', 'identifier' => 'NONEXISTENT_STUDENT_NUMBER'],
            ],
        ]);

    $resolveResponse->assertOk()
        ->assertJsonPath('rows.0.status', 'rejected')
        ->assertJsonPath('rows.0.message', 'Student could not be matched.');
});

it('supports deliberate previous-semester corrections and ensures revision idempotency', function (): void {
    $actor = assessmentTestUser();
    $course = Course::factory()->create();
    $student = Student::factory()->create(['course_id' => $course->id]);

    // Deliberate previous semester enrollment
    $pastEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2025 - 2026',
        'semester' => 2,
    ]);
    $pastTuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'enrollment_id' => $pastEnrollment->id,
        'school_year' => '2025 - 2026',
        'semester' => 2,
        'academic_year' => 1,
        'total_tuition' => 12000,
        'total_lectures' => 12000,
        'overall_tuition' => 12000,
        'total_balance' => 12000,
        'paid' => 0,
        'discount' => 0,
    ]);

    $serialized = app(TuitionAdjustmentService::class)->serialize($pastEnrollment);
    $payload = [
        'batch_key' => (string) Str::uuid(),
        'reason' => 'Prior semester audit reconciliation',
        'rows' => [[
            'client_row_id' => 'past-sem-1',
            'enrollment_id' => $pastEnrollment->id,
            'tuition_id' => $pastTuition->id,
            'state_hash' => $serialized['state_hash'],
            'total_fees' => 11000,
            'opening_paid' => 0,
            'balance' => 11000,
            'lecture' => 11000,
            'installments' => ['prelim' => 3000, 'midterm' => 3000, 'finals' => 5000],
        ]],
    ];

    $response = $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), $payload)
        ->assertSuccessful()
        ->assertJsonPath('rows.0.status', 'recorded');

    expect($pastTuition->refresh()->assessmentRevisions()->count())->toBe(1);

    // Resubmitting same batch payload returns duplicate without creating a second revision
    $duplicateResponse = $this->actingAs($actor)
        ->postJson(portalUrlForAdministrators('/administrators/finance/tuition-adjustments/batch'), $payload)
        ->assertSuccessful()
        ->assertJsonPath('rows.0.status', 'duplicate');

    expect($pastTuition->refresh()->assessmentRevisions()->count())->toBe(1);
});
