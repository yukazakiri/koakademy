<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\TuitionAdjustment;
use App\Models\TuitionAdjustmentBatch;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class TuitionAdjustmentService
{
    public function __construct(
        private EnrollmentBillingService $billing,
        private TuitionPaymentScheduleSettingsService $scheduleSettings,
        private TuitionAdjustmentNotificationService $notifications,
    ) {}

    /** @param list<array<string, mixed>> $rows @return array<string, mixed> */
    public function applyBatch(User $actor, string $batchKey, string $reason, array $rows, string $source = 'workspace'): array
    {
        $batch = TuitionAdjustmentBatch::query()->firstOrCreate(
            ['public_id' => $batchKey],
            ['actor_user_id' => $actor->id, 'source' => $source, 'status' => 'processing'],
        );
        $results = [];

        foreach ($rows as $row) {
            $results[] = $this->applyRow($batch, $actor, $reason, $row, $source);
        }

        $counts = collect($results)->countBy('status');
        $batch->update([
            'status' => 'completed',
            'recorded_count' => $counts->get('recorded', 0),
            'duplicate_count' => $counts->get('duplicate', 0),
            'rejected_count' => $counts->get('rejected', 0),
        ]);

        return ['batch_id' => $batch->public_id, 'rows' => $results];
    }

    /** @return array<string, mixed> */
    public function serialize(StudentEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['student.Course', 'course', 'studentTuition.installments', 'additionalFees']);
        $tuition = $enrollment->studentTuition;
        if (! $tuition instanceof StudentTuition) {
            throw new RuntimeException('This enrollment has no tuition record.');
        }

        $paid = $this->billing->totalPaid($tuition);
        $position = $this->billing->accountPosition($tuition, $paid);
        $additionalFees = (float) $enrollment->additionalFees->sum('amount');
        $modularOrOther = max(0, (float) $tuition->total_tuition - (float) $tuition->total_lectures - (float) $tuition->total_laboratory);
        $installments = $tuition->installments->isNotEmpty()
            ? $tuition->installments->map(fn ($installment): array => [
                'term' => $installment->term,
                'sequence' => (int) $installment->sequence,
                'percentage' => (float) $installment->percentage,
                'amount' => (float) $installment->amount,
                'source' => $installment->source,
            ])->values()->all()
            : $this->scheduleSettings->installments($position['balance_due'], $this->studentTypeValue($enrollment->student->student_type));

        $snapshot = [
            'enrollment_id' => $enrollment->id,
            'tuition_id' => $tuition->id,
            'student_id' => $enrollment->student->id,
            'student_number' => (string) $enrollment->student->student_id,
            'student_name' => $enrollment->student->full_name,
            'student_type' => $this->studentTypeValue($enrollment->student->student_type),
            'course' => $enrollment->course?->code ?? $enrollment->student->Course?->code ?? 'N/A',
            'school_year' => $enrollment->school_year,
            'semester' => (int) $enrollment->semester,
            'academic_year' => (int) $enrollment->academic_year,
            'lecture' => (float) $tuition->total_lectures,
            'laboratory' => (float) $tuition->total_laboratory,
            'miscellaneous' => (float) $tuition->total_miscelaneous_fees,
            'modular_or_other' => $modularOrOther,
            'additional_fees' => $additionalFees,
            'assessment_adjustment' => (float) ($tuition->assessment_adjustment ?? 0),
            'discount' => (int) $tuition->discount,
            'required_downpayment' => (float) $tuition->downpayment,
            'total_fees' => (float) $tuition->overall_tuition,
            'paid' => $paid,
            ...$position,
            'installments' => $installments,
        ];
        $snapshot['state_hash'] = $this->stateHash($tuition, $paid);

        return $snapshot;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function applyRow(TuitionAdjustmentBatch $batch, User $actor, string $reason, array $row, string $source): array
    {
        $clientRowId = (string) ($row['client_row_id'] ?? Str::uuid());
        $idempotencyKey = hash('sha256', $batch->public_id.'|'.$clientRowId);
        $existing = TuitionAdjustment::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing instanceof TuitionAdjustment) {
            return ['client_row_id' => $clientRowId, 'status' => 'duplicate', 'canonical' => $existing->after_snapshot, 'warnings' => $existing->delivery_status['warnings'] ?? []];
        }

        try {
            $adjustment = DB::transaction(function () use ($batch, $actor, $reason, $row, $source, $clientRowId, $idempotencyKey): TuitionAdjustment {
                $enrollment = StudentEnrollment::query()->with(['student', 'additionalFees'])->lockForUpdate()->findOrFail((int) $row['enrollment_id']);
                $tuition = StudentTuition::query()->where('enrollment_id', $enrollment->id)->lockForUpdate()->findOrFail((int) $row['tuition_id']);
                $enrollment->setRelation('studentTuition', $tuition);
                $tuition->setRelation('enrollment', $enrollment);
                $paidBefore = $this->billing->totalPaid($tuition);

                if (! hash_equals($this->stateHash($tuition, $paidBefore), (string) ($row['state_hash'] ?? ''))) {
                    throw new RuntimeException('This tuition changed after it was loaded. Refresh the row and try again.');
                }

                $before = $this->serialize($enrollment);
                $totalFees = round((float) $row['total_fees'], 2);
                $openingPaid = round((float) $row['opening_paid'], 2);
                $balanceReference = round((float) $row['balance'], 2);
                $verifiedPaid = round($this->billing->verifiedPaid($tuition), 2);

                if ($openingPaid + 0.005 < $verifiedPaid) {
                    throw new RuntimeException('Paid / DP cannot be lower than verified cashier payments of ₱'.number_format($verifiedPaid, 2).'.');
                }
                if (abs(($totalFees - $openingPaid) - $balanceReference) > 0.009) {
                    throw new RuntimeException('Total Fees, Paid / DP, and Balance do not reconcile.');
                }

                $lecture = round((float) ($row['lecture'] ?? $tuition->total_lectures), 2);
                $laboratory = round((float) ($row['laboratory'] ?? $tuition->total_laboratory), 2);
                $miscellaneous = round((float) ($row['miscellaneous'] ?? $tuition->total_miscelaneous_fees), 2);
                $discount = (int) ($row['discount'] ?? $tuition->discount);
                $modularOrOther = max(0, (float) $tuition->total_tuition - (float) $tuition->total_lectures - (float) $tuition->total_laboratory);
                $additionalFees = (float) $enrollment->additionalFees->sum('amount');
                $totalTuition = $lecture + $laboratory + $modularOrOther;
                $componentSubtotal = $totalTuition + $miscellaneous + $additionalFees;
                $componentTotal = $componentSubtotal - ($componentSubtotal * $discount / 100);

                $tuition->forceFill([
                    'total_lectures' => $lecture,
                    'total_laboratory' => $laboratory,
                    'total_miscelaneous_fees' => $miscellaneous,
                    'total_tuition' => $totalTuition,
                    'overall_tuition' => $totalFees,
                    'assessment_adjustment' => round($totalFees - $componentTotal, 2),
                    'discount' => $discount,
                    'downpayment' => round((float) ($row['required_downpayment'] ?? $tuition->downpayment), 2),
                    'paid' => $openingPaid,
                    'paid_transaction_baseline' => $verifiedPaid,
                ])->save();
                $this->billing->syncTuitionBalance($tuition->refresh());

                $scheduleBalance = max(0, $balanceReference);
                $overrides = is_array($row['installments'] ?? null) ? $row['installments'] : null;
                $studentType = $this->studentTypeValue($enrollment->student->student_type);
                $installments = $this->scheduleSettings->installments($scheduleBalance, $studentType, $overrides);
                if (abs(collect($installments)->sum('amount') - $scheduleBalance) > 0.009) {
                    throw new RuntimeException('Prelim, Midterm, and Finals must equal the remaining balance.');
                }
                foreach ($installments as $installment) {
                    $tuition->installments()->updateOrCreate(['term' => $installment['term']], $installment);
                }

                $tuition->unsetRelation('installments');
                $enrollment->setRelation('studentTuition', $tuition->refresh());
                $after = $this->serialize($enrollment);
                $profile = $this->scheduleSettings->profile($studentType);

                $adjustment = TuitionAdjustment::query()->create([
                    'batch_id' => $batch->id,
                    'actor_user_id' => $actor->id,
                    'student_enrollment_id' => $enrollment->id,
                    'student_tuition_id' => $tuition->id,
                    'client_row_id' => $clientRowId,
                    'idempotency_key' => $idempotencyKey,
                    'source' => $source,
                    'reason' => mb_trim((string) ($row['reason'] ?? $reason)),
                    'before_snapshot' => $before,
                    'after_snapshot' => $after,
                    'configuration_snapshot' => $profile,
                    'delivery_status' => ['database' => 'pending', 'mail' => 'pending', 'warnings' => []],
                ]);

                DB::afterCommit(function () use ($adjustment): void {
                    try {
                        $this->notifications->send($adjustment);
                    } catch (Throwable $exception) {
                        report($exception);
                        $adjustment->forceFill([
                            'delivery_status' => ['database' => 'failed', 'mail' => 'failed', 'warnings' => ['Student notifications could not be queued.']],
                        ])->save();
                    }
                });

                return $adjustment;
            }, 3);

            $adjustment->refresh();

            return [
                'client_row_id' => $clientRowId,
                'status' => 'recorded',
                'adjustment_id' => $adjustment->id,
                'canonical' => $adjustment->after_snapshot,
                'warnings' => $adjustment->delivery_status['warnings'] ?? [],
                'delivery_status' => $adjustment->delivery_status,
            ];
        } catch (Throwable $exception) {
            if (! $exception instanceof RuntimeException) {
                report($exception);
            }

            return ['client_row_id' => $clientRowId, 'status' => 'rejected', 'message' => $exception->getMessage(), 'warnings' => []];
        }
    }

    private function stateHash(StudentTuition $tuition, float $paid): string
    {
        return hash('sha256', implode('|', [
            $tuition->id,
            $tuition->getRawOriginal('updated_at'),
            number_format((float) $tuition->overall_tuition, 2, '.', ''),
            number_format($paid, 2, '.', ''),
        ]));
    }

    private function studentTypeValue(mixed $studentType): string
    {
        return $studentType instanceof BackedEnum ? (string) $studentType->value : (string) $studentType;
    }
}
