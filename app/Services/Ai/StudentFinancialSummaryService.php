<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\AdditionalFee;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Services\EnrollmentBillingService;
use App\Services\GeneralSettingsService;
use Illuminate\Support\Collection;

/**
 * StudentFinancialSummaryService
 *
 * Single source of truth for "what does this student owe, what have they paid,
 * and what payments exist" answers given to AI agents and MCP clients.
 *
 * Every monetary figure is delegated to EnrollmentBillingService, which owns
 * the authoritative precedence chain (payment allocations → enrollment-linked
 * transactions → legacy settlement walk) plus the audited opening-balance
 * baseline. This service never re-derives a balance from a raw column,
 * because `student_tuition.total_balance` and `.paid` are denormalised caches
 * that go stale once the allocation ledger is used.
 */
final class StudentFinancialSummaryService
{
    /**
     * Transaction statuses the billing ledger treats as settled.
     *
     * @var array<int, string>
     */
    private const SETTLED_STATUSES = ['Paid', 'Completed', 'paid', 'completed'];

    public function __construct(
        private readonly EnrollmentBillingService $billing = new EnrollmentBillingService,
        private readonly GeneralSettingsService $settings = new GeneralSettingsService,
        private readonly InstitutionEntityResolver $entities = new InstitutionEntityResolver,
    ) {}

    /**
     * Build the full financial picture for a student.
     *
     * @return array<string, mixed>
     */
    public function summarize(
        Student $student,
        ?string $schoolYear = null,
        ?int $semester = null,
        bool $includePayments = true,
        int $paymentLimit = 10,
    ): array {
        $tuitions = $this->tuitionsFor($student, $schoolYear, $semester);

        $accounts = $tuitions->map(function (StudentTuition $tuition): array {
            $additionalFeesTotal = (float) AdditionalFee::query()
                ->where('enrollment_id', $tuition->enrollment_id)
                ->sum('amount');

            $summary = $this->billing->toSummaryArray($tuition, $additionalFeesTotal);
            $enrollment = $tuition->enrollment;

            return [
                'tuition_id' => $tuition->id,
                'enrollment_id' => $tuition->enrollment_id,
                'course' => $enrollment?->course?->name ?? null,
                'school_year' => (string) $tuition->school_year,
                'semester' => (int) $tuition->semester,
                'assessment' => [
                    'lecture_fees' => $summary['total_lectures'],
                    'laboratory_fees' => $summary['total_laboratory'],
                    'miscellaneous_fees' => $summary['total_miscelaneous_fees'],
                    'additional_fees' => $summary['additional_fees_total'],
                    'subtotal_tuition' => $summary['total_tuition'],
                    'discount_percent' => $summary['discount'],
                    'assessment_adjustment' => round((float) $tuition->assessment_adjustment, 2),
                    'total_assessed' => $summary['overall_tuition'],
                ],
                'required_downpayment' => $summary['required_downpayment'],
                'total_paid' => $summary['total_paid'],
                'balance_due' => $summary['balance_due'],
                'credit' => $summary['credit'],
                'payment_status' => $summary['status'],
                'needs_finance_review' => (bool) $tuition->needs_finance_review,
            ];
        })->values()->all();

        $totals = $this->totals($tuitions);

        $result = [
            'student' => [
                'id' => (int) $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'course' => $student->Course?->name,
                'year_level' => $student->academic_year,
            ],
            'currency' => $this->currencySymbol(),
            'academic_period_filter' => [
                'school_year' => $schoolYear ?? $this->settings->getCurrentSchoolYearString(),
                'semester' => $semester ?? $this->settings->getCurrentSemester(),
                'applied' => $schoolYear !== null || $semester !== null,
            ],
            'totals' => $totals,
            'accounts' => $accounts,
            'account_count' => count($accounts),
        ];

        if ($includePayments) {
            $result['payments'] = $this->payments($student, $schoolYear, $semester, $paymentLimit);
        }

        if ($accounts === []) {
            $result['message'] = 'No tuition assessment exists for this student in the selected period. Check ListStudentEnrollmentsTool for enrollment records and academic period.';
        }

        return $result;
    }

    /**
     * Aggregate position across every returned assessment.
     *
     * @param  Collection<int, StudentTuition>  $tuitions
     * @return array<string, float|int>
     */
    private function totals(Collection $tuitions): array
    {
        if ($tuitions->isEmpty()) {
            return [
                'total_assessed' => 0.0,
                'total_paid' => 0.0,
                'balance_due' => 0.0,
                'credit' => 0.0,
                'outstanding_terms' => 0,
            ];
        }

        $totalPaid = $this->billing->aggregatePaid($tuitions);

        $balanceDue = 0.0;
        $credit = 0.0;
        $totalAssessed = 0.0;
        $outstandingTerms = 0;

        foreach ($tuitions as $tuition) {
            $position = $this->billing->accountPosition($tuition);
            $balanceDue += $position['balance_due'];
            $credit += $position['credit'];
            $totalAssessed += (float) $tuition->overall_tuition;

            if ($position['balance_due'] > 0.0) {
                $outstandingTerms++;
            }
        }

        return [
            'total_assessed' => round($totalAssessed, 2),
            'total_paid' => $totalPaid,
            'balance_due' => round($balanceDue, 2),
            'credit' => round($credit, 2),
            'outstanding_terms' => $outstandingTerms,
        ];
    }

    /**
     * Tuition assessments for a student, newest academic period first.
     *
     * Two lookups are needed because historical rows may carry a null
     * `student_id` and be reachable only through their enrollment.
     *
     * @return Collection<int, StudentTuition>
     */
    private function tuitionsFor(Student $student, ?string $schoolYear, ?int $semester): Collection
    {
        $enrollmentIds = StudentEnrollment::withTrashed()
            ->where('student_id', (string) $student->id)
            ->pluck('id')
            ->all();

        $query = StudentTuition::query()
            ->with(['enrollment.course', 'enrollment.additionalFees'])
            ->where(function ($builder) use ($student, $enrollmentIds): void {
                $builder->where('student_id', $student->id);

                if ($enrollmentIds !== []) {
                    $builder->orWhereIn('enrollment_id', $enrollmentIds);
                }
            });

        if ($schoolYear !== null) {
            $query->whereIn('school_year', $this->entities->schoolYearVariants($schoolYear));
        }

        if ($semester !== null) {
            $query->where('semester', $semester);
        }

        return $query->orderByDesc('school_year')->orderByDesc('semester')->get();
    }

    /**
     * Settled payment records, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function payments(Student $student, ?string $schoolYear, ?int $semester, int $limit): array
    {
        $limit = max(1, min(50, $limit));

        $enrollmentIds = StudentEnrollment::withTrashed()
            ->where('student_id', (string) $student->id)
            ->pluck('id')
            ->all();

        $query = StudentTransaction::query()
            ->with([
                'transaction:id,transaction_number,transaction_date,payment_method,settlements,description,invoicenumber,status',
                'enrollment' => fn ($relation) => $relation->withTrashed(),
            ])
            ->where(function ($builder) use ($student, $enrollmentIds): void {
                $builder->where('student_id', $student->id);

                if ($enrollmentIds !== []) {
                    $builder->orWhereIn('student_enrollment_id', $enrollmentIds);
                }
            })
            ->whereIn('status', self::SETTLED_STATUSES)
            ->orderByDesc('id');

        if ($schoolYear === null && $semester === null) {
            $query->limit($limit);
        }

        $records = $query->get()
            ->filter(function (StudentTransaction $record) use ($schoolYear, $semester): bool {
                if ($schoolYear === null && $semester === null) {
                    return true;
                }

                $enrollment = $record->relationLoaded('enrollment')
                    ? $record->enrollment
                    : StudentEnrollment::withTrashed()->find($record->enrollment_id);

                if (! $enrollment instanceof StudentEnrollment) {
                    return false;
                }

                if ($semester !== null && (int) $enrollment->semester !== $semester) {
                    return false;
                }

                if ($schoolYear === null) {
                    return true;
                }

                return in_array(
                    mb_trim((string) $enrollment->school_year),
                    $this->entities->schoolYearVariants($schoolYear),
                    true,
                );
            })
            ->take($limit)
            ->values();

        return $records->map(function (StudentTransaction $record): array {
            $transaction = $record->transaction;
            $settlements = is_array($transaction->settlements) ? $transaction->settlements : [];

            return [
                'student_transaction_id' => $record->id,
                'transaction_number' => $transaction->transaction_number,
                'reference_number' => $transaction->invoicenumber,
                'date' => $transaction->transaction_date?->toDateString()
                    ?? $record->created_at?->toDateString(),
                'method' => $transaction->payment_method,
                'status' => $record->status,
                'amount' => round((float) $record->amount, 2),
                'tuition_portion' => round((float) ($settlements['tuition_fee'] ?? 0), 2),
                'other_charges' => round(
                    max(0.0, (float) $record->amount - (float) ($settlements['tuition_fee'] ?? 0)),
                    2,
                ),
                'enrollment_id' => $record->student_enrollment_id,
            ];
        })->all();
    }

    private function currencySymbol(): string
    {
        $setting = \App\Models\GeneralSetting::query()->first();

        $symbol = $setting?->currency ?? 'PHP';

        return $symbol === 'PHP' ? 'PHP' : (string) $symbol;
    }
}
