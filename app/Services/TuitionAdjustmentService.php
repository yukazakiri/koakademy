<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\GenerateAssessmentPdfJob;
use App\Models\AssessmentRevision;
use App\Models\FeeScheduleVersion;
use App\Models\Resource;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\TuitionAdjustment;
use App\Models\TuitionAdjustmentBatch;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class TuitionAdjustmentService
{
    public function __construct(
        private EnrollmentBillingService $billing,
        private TuitionPaymentScheduleSettingsService $scheduleSettings,
        private TuitionAdjustmentNotificationService $notifications,
        private AssessmentCalculationService $calculator,
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

    /**
     * Preview differences for selected student accounts before applying revisions.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: list<array<string, mixed>>}
     */
    public function previewBatch(array $rows, string $calculationMethod = 'reconciled_assessment', ?int $feeScheduleVersionId = null): array
    {
        $feeSchedule = $feeScheduleVersionId
            ? FeeScheduleVersion::query()->find($feeScheduleVersionId)
            : null;

        $previews = [];
        foreach ($rows as $row) {
            $previews[] = $this->previewRow($row, $calculationMethod, $feeSchedule);
        }

        return ['rows' => $previews];
    }

    /**
     * Preview single account difference.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function previewRow(array $row, string $calculationMethod = 'reconciled_assessment', ?FeeScheduleVersion $feeSchedule = null): array
    {
        $clientRowId = (string) ($row['client_row_id'] ?? Str::uuid());

        try {
            $enrollment = StudentEnrollment::query()
                ->with(['student.Course', 'course', 'additionalFees', 'subjectsEnrolled.subject'])
                ->findOrFail((int) $row['enrollment_id']);

            $tuition = StudentTuition::query()
                ->where('enrollment_id', $enrollment->id)
                ->with('installments')
                ->findOrFail((int) $row['tuition_id']);

            $enrollment->setRelation('studentTuition', $tuition);
            $tuition->setRelation('enrollment', $enrollment);

            $current = $this->serialize($enrollment);
            $discount = (int) ($row['discount'] ?? $tuition->discount);
            $verifiedPaid = round($this->billing->verifiedPaid($tuition), 2);
            $openingPaid = isset($row['opening_paid'])
                ? round((float) $row['opening_paid'], 2)
                : round((float) ($tuition->getRawOriginal('paid') ?? 0), 2);

            if ($openingPaid + 0.005 < $verifiedPaid) {
                throw new RuntimeException('Paid / DP cannot be lower than verified cashier payments of ₱'.number_format($verifiedPaid, 2).'.');
            }

            if ($calculationMethod === 'fee_rates') {
                $calc = $this->calculator->calculateFromEnrolledSubjects(
                    enrollment: $enrollment,
                    feeSchedule: $feeSchedule,
                    discountPercentage: $discount,
                    miscellaneousOverride: isset($row['miscellaneous']) ? (float) $row['miscellaneous'] : null,
                    approvedTotal: isset($row['total_fees']) ? (float) $row['total_fees'] : null,
                );
                $lecture = $calc['discounted_lecture'];
                $grossLecture = $calc['gross_lecture'];
                $laboratory = $calc['laboratory'];
                $modularOrOther = $calc['modular'];
                $miscellaneous = $calc['miscellaneous'];
                $additionalFees = $calc['additional_fees'];
                $totalFees = $calc['overall_tuition'];
                $assessmentAdjustment = $calc['assessment_adjustment'];
            } else {
                $lectureComp = $this->calculator->resolveLecture(
                    providedGross: isset($row['gross_lecture']) ? (float) $row['gross_lecture'] : null,
                    providedLecture: isset($row['lecture']) ? (float) $row['lecture'] : null,
                    discountPercentage: $discount,
                    existingTuition: $tuition,
                );
                $lecture = $lectureComp['discounted_lecture'];
                $grossLecture = $lectureComp['gross_lecture'];
                $laboratory = round((float) ($row['laboratory'] ?? $tuition->total_laboratory), 2);
                $miscellaneous = round((float) ($row['miscellaneous'] ?? $tuition->total_miscelaneous_fees), 2);
                $modularOrOther = max(0, (float) $tuition->total_tuition - (float) $tuition->total_lectures - (float) $tuition->total_laboratory);
                $additionalFees = (float) $enrollment->additionalFees->sum('amount');
                $totalFees = isset($row['total_fees'])
                    ? round((float) $row['total_fees'], 2)
                    : (float) $tuition->overall_tuition;

                $calc = $this->calculator->calculateComponents(
                    grossLecture: $grossLecture,
                    laboratory: $laboratory,
                    modular: $modularOrOther,
                    miscellaneous: $miscellaneous,
                    additionalFees: $additionalFees,
                    discountPercentage: $discount,
                    totalFees: $totalFees,
                );
                $assessmentAdjustment = $calc['assessment_adjustment'];
            }

            $proposedBalance = round($totalFees - $openingPaid, 2);
            $scheduleBalance = max(0, $proposedBalance);
            $studentType = $this->studentTypeValue($enrollment->student->student_type);
            $installments = $this->scheduleSettings->installments(
                $scheduleBalance,
                $studentType,
                is_array($row['installments'] ?? null) ? $row['installments'] : null
            );

            $fingerprint = $this->calculator->fingerprint([
                'enrollment_id' => $enrollment->id,
                'tuition_id' => $tuition->id,
                'total_fees' => $totalFees,
                'lecture' => $lecture,
                'laboratory' => $laboratory,
                'miscellaneous' => $miscellaneous,
                'discount' => $discount,
                'paid' => $openingPaid,
                'balance' => $proposedBalance,
            ]);

            $feeDelta = round($totalFees - (float) $current['total_fees'], 2);
            $balanceDelta = round($proposedBalance - (float) $current['balance_due'], 2);

            $explanation = sprintf(
                'Assessment %s from ₱%s to ₱%s (%s₱%s). Balance %s by ₱%s.',
                $feeDelta >= 0 ? 'increased' : 'decreased',
                number_format((float) $current['total_fees'], 2),
                number_format($totalFees, 2),
                $feeDelta >= 0 ? '+' : '',
                number_format($feeDelta, 2),
                $balanceDelta >= 0 ? 'increased' : 'reduced',
                number_format(abs($balanceDelta), 2)
            );

            return [
                'client_row_id' => $clientRowId,
                'status' => 'ready',
                'enrollment_id' => $enrollment->id,
                'tuition_id' => $tuition->id,
                'student_number' => (string) $enrollment->student->student_id,
                'student_name' => (string) $enrollment->student->full_name,
                'current' => $current,
                'proposed' => [
                    'gross_lecture' => $grossLecture,
                    'lecture' => $lecture,
                    'laboratory' => $laboratory,
                    'miscellaneous' => $miscellaneous,
                    'modular_or_other' => $modularOrOther,
                    'additional_fees' => $additionalFees,
                    'discount' => $discount,
                    'total_fees' => $totalFees,
                    'opening_paid' => $openingPaid,
                    'balance' => $proposedBalance,
                    'balance_due' => max(0, $proposedBalance),
                    'credit' => max(0, -$proposedBalance),
                    'assessment_adjustment' => $assessmentAdjustment,
                    'installments' => $installments,
                    'required_downpayment' => round((float) ($row['required_downpayment'] ?? $tuition->downpayment), 2),
                ],
                'differences' => [
                    'total_fees_delta' => $feeDelta,
                    'balance_delta' => $balanceDelta,
                    'lecture_delta' => round($lecture - (float) $current['lecture'], 2),
                    'laboratory_delta' => round($laboratory - (float) $current['laboratory'], 2),
                    'miscellaneous_delta' => round($miscellaneous - (float) $current['miscellaneous'], 2),
                    'discount_delta' => $discount - (int) $current['discount'],
                ],
                'fingerprint' => $fingerprint,
                'explanation' => $explanation,
            ];
        } catch (Throwable $e) {
            return [
                'client_row_id' => $clientRowId,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get immutable revision history for a student tuition account.
     *
     * @return list<array<string, mixed>>
     */
    public function history(int $tuitionId): array
    {
        $revisions = AssessmentRevision::query()
            ->where('student_tuition_id', $tuitionId)
            ->with(['actor', 'feeScheduleVersion', 'pdfResource'])
            ->orderByDesc('revision_number')
            ->get();

        return $revisions->map(function (AssessmentRevision $rev): array {
            $pdfUrl = null;
            if ($rev->pdfResource && $rev->pdfResource->file_path) {
                $disk = $rev->pdfResource->disk ?? config('filesystems.default');
                $pdfUrl = Storage::disk($disk)->url($rev->pdfResource->file_path);
            }

            return [
                'id' => $rev->id,
                'public_id' => $rev->public_id,
                'revision_number' => $rev->revision_number,
                'status' => $rev->status,
                'source' => $rev->source,
                'calculation_method' => $rev->calculation_method,
                'reason' => $rev->reason,
                'actor_name' => $rev->actor?->name ?? 'System',
                'created_at' => $rev->created_at?->format('Y-m-d H:i:s'),
                'gross_lecture' => $rev->gross_lecture,
                'discount_percentage' => $rev->discount_percentage,
                'discount_amount' => $rev->discount_amount,
                'discounted_lecture' => $rev->discounted_lecture,
                'laboratory' => $rev->laboratory,
                'modular' => $rev->modular,
                'total_tuition' => $rev->total_tuition,
                'miscellaneous' => $rev->miscellaneous,
                'additional_fees' => $rev->additional_fees,
                'assessment_adjustment' => $rev->assessment_adjustment,
                'overall_tuition' => $rev->overall_tuition,
                'required_downpayment' => $rev->required_downpayment,
                'opening_paid' => $rev->opening_paid,
                'verified_paid' => $rev->verified_paid,
                'total_paid' => $rev->total_paid,
                'balance_due' => $rev->balance_due,
                'credit' => $rev->credit,
                'installments' => $rev->installments,
                'fingerprint' => $rev->fingerprint,
                'pdf_url' => $pdfUrl,
            ];
        })->all();
    }

    /** @return array<string, mixed> */
    public function serialize(StudentEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['student.Course', 'course', 'studentTuition.installments', 'additionalFees']);
        $tuition = $enrollment->studentTuition;
        if (! $tuition instanceof StudentTuition) {
            throw new RuntimeException('This enrollment has no tuition record.');
        }
        if (! $tuition->relationLoaded('enrollment')) {
            $tuition->setRelation('enrollment', $enrollment);
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

        $grossLecture = (float) ($tuition->gross_lecture ?? 0);
        if ($grossLecture <= 0.0) {
            $grossLecture = $tuition->discount > 0 && $tuition->discount < 100
                ? round((float) $tuition->total_lectures / (1 - $tuition->discount / 100), 2)
                : (float) $tuition->total_lectures;
        }
        $discountAmount = round($grossLecture * ($tuition->discount / 100), 2);

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
            'gross_lecture' => $grossLecture,
            'discount_amount' => $discountAmount,
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
            'active_revision_id' => $tuition->active_revision_id,
            'needs_finance_review' => (bool) $tuition->needs_finance_review,
            ...$position,
            'installments' => $installments,
        ];
        $snapshot['state_hash'] = $this->stateHash($tuition, $paid);
        $snapshot['fingerprint'] = $this->calculator->fingerprint($snapshot);

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
                $enrollment = StudentEnrollment::query()->with(['student', 'additionalFees', 'subjectsEnrolled.subject'])->lockForUpdate()->findOrFail((int) $row['enrollment_id']);
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

                $discount = (int) ($row['discount'] ?? $tuition->discount);
                $calculationMethod = (string) ($row['calculation_method'] ?? 'reconciled_assessment');
                $feeSchedule = ! empty($row['fee_schedule_version_id'])
                    ? FeeScheduleVersion::query()->find((int) $row['fee_schedule_version_id'])
                    : null;

                if ($calculationMethod === 'fee_rates') {
                    $calc = $this->calculator->calculateFromEnrolledSubjects(
                        enrollment: $enrollment,
                        feeSchedule: $feeSchedule,
                        discountPercentage: $discount,
                        miscellaneousOverride: isset($row['miscellaneous']) ? (float) $row['miscellaneous'] : null,
                        approvedTotal: $totalFees,
                    );
                    $lecture = $calc['discounted_lecture'];
                    $grossLecture = $calc['gross_lecture'];
                    $laboratory = $calc['laboratory'];
                    $modularOrOther = $calc['modular'];
                    $miscellaneous = $calc['miscellaneous'];
                    $additionalFees = $calc['additional_fees'];
                    $totalTuition = $calc['total_tuition'];
                    $assessmentAdjustment = $calc['assessment_adjustment'];
                } else {
                    $laboratory = round((float) ($row['laboratory'] ?? $tuition->total_laboratory), 2);
                    $miscellaneous = round((float) ($row['miscellaneous'] ?? $tuition->total_miscelaneous_fees), 2);
                    $modularOrOther = max(0, (float) $tuition->total_tuition - (float) $tuition->total_lectures - (float) $tuition->total_laboratory);
                    $additionalFees = (float) $enrollment->additionalFees->sum('amount');

                    $lectureComp = $this->calculator->resolveLecture(
                        providedGross: isset($row['gross_lecture']) ? (float) $row['gross_lecture'] : null,
                        providedLecture: isset($row['lecture']) ? (float) $row['lecture'] : null,
                        discountPercentage: $discount,
                        existingTuition: $tuition,
                    );
                    $lecture = $lectureComp['discounted_lecture'];
                    $grossLecture = $lectureComp['gross_lecture'];
                    $totalTuition = round($lecture + $laboratory + $modularOrOther, 2);

                    $calc = $this->calculator->calculateComponents(
                        grossLecture: $grossLecture,
                        laboratory: $laboratory,
                        modular: $modularOrOther,
                        miscellaneous: $miscellaneous,
                        additionalFees: $additionalFees,
                        discountPercentage: $discount,
                        totalFees: $totalFees,
                    );
                    $assessmentAdjustment = $calc['assessment_adjustment'];
                }

                $tuition->forceFill([
                    'total_lectures' => $lecture,
                    'gross_lecture' => $grossLecture,
                    'total_laboratory' => $laboratory,
                    'total_miscelaneous_fees' => $miscellaneous,
                    'total_tuition' => $totalTuition,
                    'overall_tuition' => $totalFees,
                    'assessment_adjustment' => $assessmentAdjustment,
                    'discount' => $discount,
                    'downpayment' => round((float) ($row['required_downpayment'] ?? $tuition->downpayment), 2),
                    'paid' => $openingPaid,
                    'paid_transaction_baseline' => $verifiedPaid,
                    'needs_finance_review' => false,
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

                $previousRevision = $tuition->activeRevision ?? $tuition->assessmentRevisions()->first();
                $revisionNumber = ($tuition->assessmentRevisions()->max('revision_number') ?? 0) + 1;
                $positionAfter = $this->billing->accountPosition($tuition, $this->billing->totalPaid($tuition));

                $revision = AssessmentRevision::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'student_tuition_id' => $tuition->id,
                    'student_enrollment_id' => $enrollment->id,
                    'fee_schedule_version_id' => $feeSchedule?->id,
                    'actor_user_id' => $actor->id,
                    'predecessor_id' => $previousRevision?->id,
                    'revision_number' => $revisionNumber,
                    'calculation_method' => $calculationMethod,
                    'status' => 'approved',
                    'source' => $source,
                    'reason' => mb_trim((string) ($row['reason'] ?? $reason)),
                    'gross_lecture' => $grossLecture,
                    'discount_percentage' => $discount,
                    'discount_amount' => $calc['discount_amount'],
                    'discounted_lecture' => $lecture,
                    'laboratory' => $laboratory,
                    'modular' => $modularOrOther,
                    'total_tuition' => $totalTuition,
                    'miscellaneous' => $miscellaneous,
                    'additional_fees' => $additionalFees,
                    'assessment_adjustment' => $assessmentAdjustment,
                    'overall_tuition' => $totalFees,
                    'required_downpayment' => round((float) ($row['required_downpayment'] ?? $tuition->downpayment), 2),
                    'opening_paid' => $openingPaid,
                    'verified_paid' => $verifiedPaid,
                    'total_paid' => $this->billing->totalPaid($tuition),
                    'balance_due' => (float) $positionAfter['balance_due'],
                    'credit' => (float) $positionAfter['credit'],
                    'installments' => $installments,
                    'calculation_inputs' => $row['calculation_inputs'] ?? null,
                    'fingerprint' => $this->calculator->fingerprint([
                        'enrollment_id' => $enrollment->id,
                        'tuition_id' => $tuition->id,
                        'total_fees' => $totalFees,
                        'lecture' => $lecture,
                        'laboratory' => $laboratory,
                        'miscellaneous' => $miscellaneous,
                        'discount' => $discount,
                        'paid' => $openingPaid,
                        'balance' => $balanceReference,
                    ]),
                ]);

                $tuition->forceFill(['active_revision_id' => $revision->id])->save();

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

                $revision->update(['tuition_adjustment_id' => $adjustment->id]);

                $hasCurrentAssessment = Resource::query()
                    ->where('resourceable_id', $enrollment->id)
                    ->where('resourceable_type', StudentEnrollment::class)
                    ->where('type', 'assessment')
                    ->exists();

                DB::afterCommit(function () use ($adjustment, $enrollment, $hasCurrentAssessment): void {
                    try {
                        $this->notifications->send($adjustment);
                    } catch (Throwable $exception) {
                        report($exception);
                        $adjustment->forceFill([
                            'delivery_status' => ['database' => 'failed', 'mail' => 'failed', 'warnings' => ['Student notifications could not be queued.']],
                        ])->save();
                    }

                    if ($hasCurrentAssessment) {
                        GenerateAssessmentPdfJob::dispatch(
                            $enrollment->id,
                            'tuition-adjustment-'.$adjustment->id,
                            false,
                            true,
                            false,
                        );
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
