<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;

final class EnrollmentBillingService
{
    public function toSummaryArray(StudentTuition $tuition, float $additionalFeesTotal = 0.0): array
    {
        $totalPaid = $this->totalPaid($tuition);
        $balanceDue = $this->balanceDue($tuition, $totalPaid);

        return [
            'id' => $tuition->id,
            'total_tuition' => (float) $tuition->total_tuition,
            'total_lectures' => (float) $tuition->total_lectures,
            'total_laboratory' => (float) $tuition->total_laboratory,
            'total_miscelaneous_fees' => (float) $tuition->total_miscelaneous_fees,
            'additional_fees_total' => $additionalFeesTotal,
            'discount' => (int) $tuition->discount,
            'downpayment' => (float) $tuition->downpayment,
            'required_downpayment' => (float) $tuition->downpayment,
            'overall_tuition' => (float) $tuition->overall_tuition,
            'total_balance' => $balanceDue,
            'balance_due' => $balanceDue,
            'total_paid' => $totalPaid,
            'status' => $this->paymentStatus($tuition, $totalPaid),
        ];
    }

    public function totalPaid(StudentTuition $tuition): float
    {
        $manualPaid = (float) ($tuition->getRawOriginal('paid') ?? 0);
        $enrollment = $tuition->relationLoaded('enrollment')
            ? $tuition->enrollment
            : $tuition->enrollment()->first();
        if ($enrollment instanceof StudentEnrollment) {
            $linkedPayments = StudentTransaction::query()
                ->with('transaction')
                ->where('student_enrollment_id', $enrollment->id)
                ->whereIn('status', ['Paid', 'Completed', 'paid', 'completed'])
                ->get();
            if ($linkedPayments->isNotEmpty()) {
                $linkedPaid = $linkedPayments->sum(
                    fn (StudentTransaction $link): float => (float) $link->amount,
                );

                return max($manualPaid, (float) $linkedPaid);
            }
            if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
                return $manualPaid;
            }
        }

        $student = $this->resolveStudent($tuition);
        if (! $student instanceof Student) {
            return $manualPaid;
        }

        $schoolYear = $this->normalizeSchoolYear($tuition->school_year);
        if ($schoolYear === null) {
            return $manualPaid;
        }

        $semester = (int) $tuition->semester;
        if (! in_array($semester, [1, 2], true)) {
            return $manualPaid;
        }

        $transactionPaid = (float) $student->Transaction()
            ->forAcademicPeriod($schoolYear, $semester)
            ->whereIn('transactions.status', ['Paid', 'Completed', 'paid', 'completed'])
            ->get()
            ->sum(fn (Transaction $transaction): float => $this->tuitionPaymentFromSettlements($transaction));

        return max($manualPaid, $transactionPaid);
    }

    public function balanceDue(StudentTuition $tuition, ?float $totalPaid = null): float
    {
        $paid = $totalPaid ?? $this->totalPaid($tuition);

        return max(0.0, (float) $tuition->overall_tuition - $paid);
    }

    public function paymentStatus(StudentTuition $tuition, ?float $totalPaid = null): string
    {
        $paid = $totalPaid ?? $this->totalPaid($tuition);
        $balanceDue = $this->balanceDue($tuition, $paid);

        if ($balanceDue <= 0.0) {
            return 'Paid';
        }

        return $paid > 0.0 ? 'Downpayment' : 'Pending';
    }

    public function syncTuitionBalance(StudentTuition $tuition, ?float $requiredDownpayment = null): StudentTuition
    {
        $totalPaid = $this->totalPaid($tuition);

        $tuition->forceFill([
            'total_balance' => $this->balanceDue($tuition, $totalPaid),
            'status' => $this->paymentStatus($tuition, $totalPaid),
        ]);

        if ($requiredDownpayment !== null) {
            $tuition->downpayment = $requiredDownpayment;
        }

        if ($tuition->isDirty()) {
            $tuition->save();
        }

        return $tuition->refresh();
    }

    private function tuitionPaymentFromSettlements(Transaction $transaction): float
    {
        $settlements = $transaction->settlements;

        if (is_string($settlements)) {
            $settlements = json_decode($settlements, true);
        }

        if (! is_array($settlements)) {
            return 0.0;
        }

        return (float) ($settlements['tuition_fee'] ?? 0);
    }

    private function resolveStudent(StudentTuition $tuition): ?Student
    {
        if ($tuition->relationLoaded('student') && $tuition->student instanceof Student) {
            return $tuition->student;
        }

        if ($tuition->student_id) {
            $student = Student::query()->find($tuition->student_id);
            if ($student instanceof Student) {
                return $student;
            }
        }

        if ($tuition->relationLoaded('enrollment') && $tuition->enrollment?->student instanceof Student) {
            return $tuition->enrollment->student;
        }

        return $tuition->enrollment?->student;
    }

    private function normalizeSchoolYear(mixed $schoolYear): ?string
    {
        if (! is_string($schoolYear) || $schoolYear === '') {
            return null;
        }

        $normalized = str_replace(' ', '', $schoolYear);

        if (preg_match('/^(\d{4})-(\d{4})$/', $normalized) !== 1) {
            return null;
        }

        return $normalized;
    }
}
