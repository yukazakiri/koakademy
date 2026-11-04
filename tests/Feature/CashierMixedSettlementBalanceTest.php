<?php

declare(strict_types=1);

use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\PaymentAllocation;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Modules\Cashier\Filament\Pages\Cashier;

beforeEach(function (): void {
    Bus::fake();

    GeneralSetting::factory()->create([
        'school_starting_date' => '2026-06-01',
        'school_ending_date' => '2027-03-31',
        'semester' => 1,
    ]);
});

/**
 * @return array{0: User, 1: Student, 2: StudentTuition, 3: StudentEnrollment}
 */
function mixedSettlementFixture(): array
{
    $course = Course::factory()->create();
    $student = Student::factory()->create([
        'course_id' => $course->id,
        'academic_year' => 2,
    ]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'academic_year' => 2,
        'school_year' => '2026 - 2027',
        'semester' => 1,
    ]);
    $tuition = StudentTuition::query()->create([
        'enrollment_id' => $enrollment->id,
        'student_id' => $student->id,
        'total_tuition' => 4000,
        'total_balance' => 5000,
        'total_lectures' => 3000,
        'total_laboratory' => 1000,
        'total_miscelaneous_fees' => 1000,
        'discount' => 0,
        'downpayment' => 500,
        'overall_tuition' => 5000,
        'semester' => 1,
        'school_year' => '2026 - 2027',
        'academic_year' => 2,
        'status' => 'Pending',
    ]);

    return [User::factory()->create(), $student, $tuition, $enrollment];
}

/**
 * @param  array<string, float|int>  $settlements
 */
function postMixedSettlementPayment(User $cashier, Student $student, array $settlements, string $invoice = 'OR-MIXED-1'): void
{
    test()->actingAs($cashier);

    $page = app(Cashier::class);
    $page->data = [
        'selectedStudent' => $student,
        'description' => 'Mixed settlement',
        'selectedSchoolYear' => '2026 - 2027',
        'selectedSemester' => 1,
        'settlements' => $settlements,
        'invoicenumber' => $invoice,
        'signature' => null,
    ];

    $page->create();
}

it('applies only the tuition portion of a mixed cashier settlement to the balance', function (): void {
    [$cashier, $student, $tuition] = mixedSettlementFixture();

    postMixedSettlementPayment($cashier, $student, [
        'tuition_fee' => 1000,
        'others' => 500,
    ]);

    $tuition->refresh();
    $link = StudentTransaction::query()->sole();

    expect((float) $link->amount)->toBe(1500.0)
        ->and((float) $tuition->total_balance)->toBe(4000.0)
        ->and($tuition->status)->toBe('Downpayment');
});

it('counts a cashier payment alongside existing tuition allocations', function (): void {
    [$cashier, $student, $tuition, $enrollment] = mixedSettlementFixture();

    $receipt = Transaction::query()->create([
        'description' => 'Enrollment downpayment',
        'payment_method' => 'Cash',
        'status' => 'Paid',
        'transaction_date' => now(),
        'settlements' => ['tuition_fee' => 1000],
        'invoicenumber' => 'OR-MIXED-0',
        'user_id' => $cashier->id,
    ]);
    $existingLink = StudentTransaction::query()->create([
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'transaction_id' => $receipt->id,
        'amount' => 1000,
        'status' => 'Paid',
    ]);
    PaymentAllocation::query()->create([
        'transaction_id' => $receipt->id,
        'student_transaction_id' => $existingLink->id,
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'student_tuition_id' => $tuition->id,
        'charge_category' => 'tuition',
        'target_type' => 'assessment',
        'amount' => 1000,
    ]);

    postMixedSettlementPayment($cashier, $student, ['tuition_fee' => 500], 'OR-MIXED-2');

    $tuition->refresh();

    expect((float) $tuition->total_balance)->toBe(3500.0)
        ->and($tuition->status)->toBe('Downpayment');
});
