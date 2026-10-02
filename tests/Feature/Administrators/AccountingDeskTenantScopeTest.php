<?php

declare(strict_types=1);

use App\Dashboards\AccountingDesk;
use App\Dashboards\DashboardContext;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Carbon\CarbonImmutable;

/**
 * Two guards on the accounting desk.
 *
 * Tenant isolation: student_transactions, transactions and student_tuition carry no
 * school_id and none of those models has the BelongsToSchool scope, so without an explicit
 * filter a cashier in a multi-school installation would see another school's money.
 *
 * Settlement arithmetic: raw_total_amount is an accessor over the JSON `settlements` column,
 * so it reads as zero on a row that never selected that column.
 */
function accountingContext(?School $school): DashboardContext
{
    $settings = app(GeneralSettingsService::class);

    return new DashboardContext(
        $school,
        $settings->getCurrentSchoolYearString(),
        $settings->getCurrentSemester(),
        CarbonImmutable::now()->subYear()->startOfDay(),
        CarbonImmutable::now()->endOfDay(),
        null,
    );
}

/**
 * Record one settled payment for a student in the given school.
 */
function settleTuition(School $school, float $amount): void
{
    $settings = app(GeneralSettingsService::class);

    $student = Student::factory()->create(['school_id' => $school->id]);

    $enrollment = StudentEnrollment::factory()->create([
        'school_id' => $school->id,
        'student_id' => $student->id,
        'school_year' => $settings->getCurrentSchoolYearString(),
        'semester' => $settings->getCurrentSemester(),
        'status' => 'enrolled',
    ]);

    $transaction = Transaction::create([
        'transaction_date' => now(),
        'settlements' => json_encode([$amount]),
        'status' => 'settled',
        'transaction_number' => 'T'.uniqid(),
        'description' => 'Tuition payment',
    ]);

    StudentTransaction::create([
        'student_id' => $student->id,
        'student_enrollment_id' => $enrollment->id,
        'transaction_id' => $transaction->id,
        'amount' => $amount,
        'status' => 'settled',
    ]);
}

it('does not aggregate another school payments into the collected total', function (): void {
    $mine = School::factory()->create();
    $theirs = School::factory()->create();

    settleTuition($mine, 5_000);
    settleTuition($theirs, 9_000);

    $payload = (new AccountingDesk())->data(
        new User(['role' => App\Enums\UserRole::Cashier]),
        accountingContext($mine),
    );

    $collected = collect($payload['kpis'])->firstWhere('label', 'Collected');

    expect((float) $collected['value'])->toBe(5_000.0);
});

it('keeps the top payers table inside the current school', function (): void {
    $mine = School::factory()->create();
    $theirs = School::factory()->create();

    settleTuition($mine, 5_000);
    settleTuition($theirs, 9_000);

    $payload = (new AccountingDesk())->data(
        new User(['role' => App\Enums\UserRole::Cashier]),
        accountingContext($mine),
    );

    $totals = collect($payload['tables'][0]['rows'])->pluck('total')->map(fn ($v): float => (float) $v);

    expect($totals)->toContain(5_000.0);
    expect($totals)->not->toContain(9_000.0);
});

it('totals the settlements instead of reporting zero for daily collections', function (): void {
    $school = School::factory()->create();

    settleTuition($school, 7_500);

    $payload = (new AccountingDesk())->data(
        new User(['role' => App\Enums\UserRole::Cashier]),
        accountingContext($school),
    );

    $series = $payload['trends'][0]['data'] ?? [];

    expect($series)->not->toBeEmpty();
    expect((float) collect($series)->sum('total'))->toBe(7_500.0);

    // Guard the specific regression: a grouped row that never selected `settlements` makes
    // the accessor read 0.
    expect(collect($series)->sum('total'))->toBeGreaterThan(0);
});
