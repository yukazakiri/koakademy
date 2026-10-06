<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use App\Services\EnrollmentBillingService;

it('returns zero total paid when school year is missing', function () {
    $student = Student::factory()->make();

    $tuition = StudentTuition::make([
        'student_id' => $student->id,
        'school_year' => null,
        'semester' => 1,
        'paid' => null,
    ]);

    $tuition->setRelation('student', $student);

    expect($tuition->total_paid)->toBe(0.0);
});

it('returns zero total paid when school year format is invalid', function () {
    $student = Student::factory()->make();

    $tuition = StudentTuition::make([
        'student_id' => $student->id,
        'school_year' => '1',
        'semester' => 1,
        'paid' => null,
    ]);

    $tuition->setRelation('student', $student);

    expect($tuition->total_paid)->toBe(0.0);
});

it('does not count the required downpayment as paid before cashier verification', function () {
    $student = Student::factory()->create([
        'id' => fake()->numberBetween(100000, 999999),
    ]);

    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 6500,
        'total_lectures' => 6500,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'overall_tuition' => 10000,
        'total_balance' => 10000,
        'downpayment' => 2500,
        'discount' => 0,
    ]);

    $billing = app(EnrollmentBillingService::class);

    expect($tuition->total_paid)->toBe(0.0)
        ->and($billing->balanceDue($tuition))->toBe(10000.0);
});

it('deducts an actual cashier payment only once from the balance', function () {
    $student = Student::factory()->create([
        'id' => fake()->numberBetween(100000, 999999),
    ]);

    $tuition = StudentTuition::query()->create([
        'student_id' => $student->id,
        'school_year' => '2026 - 2027',
        'semester' => 1,
        'academic_year' => 1,
        'total_tuition' => 6500,
        'total_lectures' => 6500,
        'total_laboratory' => 0,
        'total_miscelaneous_fees' => 3500,
        'overall_tuition' => 10000,
        'total_balance' => 10000,
        'downpayment' => 2500,
        'discount' => 0,
    ]);

    $transaction = Transaction::query()->create([
        'description' => 'Downpayment for student Tuition',
        'payment_method' => 'Cash',
        'settlements' => ['tuition_fee' => 2500],
        'status' => 'Paid',
        'invoicenumber' => 'INV-DOUBLE-001',
        'transaction_date' => '2026-04-15 12:00:00',
    ]);

    $transaction->forceFill([
        'created_at' => '2026-04-15 12:00:00',
        'updated_at' => '2026-04-15 12:00:00',
    ])->save();

    StudentTransaction::query()->create([
        'student_id' => $student->id,
        'transaction_id' => $transaction->id,
        'amount' => 2500,
        'status' => 'Paid',
    ]);

    $tuition = app(EnrollmentBillingService::class)->syncTuitionBalance($tuition->refresh());

    expect($tuition->total_paid)->toBe(2500.0)
        ->and((float) $tuition->total_balance)->toBe(7500.0)
        ->and((float) $tuition->downpayment)->toBe(2500.0);
});
