<?php

declare(strict_types=1);

use App\Enums\FinancialDeliveryStatus;
use App\Enums\FinancialDocumentStatus;
use App\Jobs\SendFinancialDocumentJob;
use App\Models\Course;
use App\Models\FinancialDocumentIssuance;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Services\EnrollmentPipelineService;
use App\Services\EnrollmentService;
use App\Services\FinancialDocumentViewDataService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;

it('undo cashier verification reverses linked transactions and recalculates tuition', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
        'email_from_address' => 'billing@example.test',
        'more_configs' => [
            'notification_channels' => ['enabled_channels' => ['mail']],
            'finance_documents' => [
                'automatic_receipts_enabled' => true,
                'require_paper_or_reference' => true,
                'manual_invoices_enabled' => true,
            ],
        ],
    ]);

    $course = Course::factory()->create();

    $student = Student::factory()->create([
        'id' => fake()->numberBetween(100000, 999999),
        'course_id' => $course->id,
        'email' => 'reversed-payment@example.test',
    ]);

    $pipeline = app(EnrollmentPipelineService::class);

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'status' => $pipeline->getCashierVerifiedStatus(),
    ]);

    $tuition = StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'total_tuition' => 0,
        'total_balance' => 3975,
        'total_lectures' => 975,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'discount' => 0,
        'downpayment' => 500,
        'overall_tuition' => 4475,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 1,
    ]);

    Bus::fake();
    Mail::fake();
    $transaction = Transaction::query()->create([
        'description' => 'Downpayment for student Tuition',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
        'status' => 'Paid',
        'invoicenumber' => 'INV-UNDO-001',
        'transaction_date' => '2026-04-15 12:00:00',
        'created_at' => '2026-04-15 12:00:00',
        'updated_at' => '2026-04-15 12:00:00',
    ]);

    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'student_enrollment_id' => $enrollment->id,
        'amount' => 500,
        'status' => 'Paid',
    ]);
    $receipt = FinancialDocumentIssuance::query()->with('deliveries')->sole();
    $delivery = $receipt->deliveries->sole();

    $result = app(EnrollmentService::class)->undoCashierVerification($enrollment->id);

    expect($result)->toBeTrue();

    $enrollment->refresh();
    $tuition->refresh();

    expect(Transaction::query()->whereKey($transaction->id)->exists())->toBeFalse();
    expect($pipeline->isCashierVerified($enrollment->status))->toBeFalse();
    expect((float) $tuition->downpayment)->toBe(500.0);
    expect((float) $tuition->total_balance)->toBe((float) $tuition->overall_tuition);
    expect($receipt->refresh()->status)->toBe(FinancialDocumentStatus::Revoked)
        ->and($receipt->revoked_at)->not->toBeNull()
        ->and($receipt->transaction_id)->toBeNull()
        ->and($delivery->refresh()->status)->toBe(FinancialDeliveryStatus::Cancelled);

    $this->get(portalUrlForAdministrators("/verify/finance/{$receipt->verification_token}"))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('status', 'revoked'));

    $job = new SendFinancialDocumentJob($delivery->id);
    $job->handle(app(FinancialDocumentViewDataService::class));
    $job->failed(new RuntimeException('A cancelled delivery must remain revoked.'));
    Mail::assertNothingSent();
    expect($receipt->refresh()->status)->toBe(FinancialDocumentStatus::Revoked)
        ->and($delivery->refresh()->status)->toBe(FinancialDeliveryStatus::Cancelled);
});

it('fails closed when a legacy payment cannot be attributed to one enrollment', function (): void {
    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);

    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'id' => fake()->numberBetween(100000, 999999),
        'course_id' => $course->id,
    ]);
    $pipeline = app(EnrollmentPipelineService::class);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'status' => $pipeline->getCashierVerifiedStatus(),
    ]);
    StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'total_tuition' => 0,
        'total_balance' => 3975,
        'total_lectures' => 975,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'discount' => 0,
        'downpayment' => 500,
        'overall_tuition' => 4475,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 1,
    ]);
    $transaction = Transaction::query()->create([
        'description' => 'Unlinked legacy payment',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 500],
        'status' => 'Paid',
        'invoicenumber' => 'INV-AMBIGUOUS-001',
        'transaction_date' => '2026-07-01 12:00:00',
    ]);
    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 500,
        'status' => 'Paid',
    ]);

    expect(fn () => app(EnrollmentService::class)->undoCashierVerification($enrollment->id))
        ->toThrow(App\Enrollment\Exceptions\EnrollmentTransitionException::class);

    expect($enrollment->refresh()->status)->toBe($pipeline->getCashierVerifiedStatus())
        ->and(Transaction::query()->whereKey($transaction->id)->exists())->toBeTrue();
});
