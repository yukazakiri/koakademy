<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\TransitionResult;
use App\Enrollment\Exceptions\EnrollmentTransitionException;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\StudentEnrollment;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cancellation is an administrative termination, not a workflow transition.
 *
 * It is deliberately available for both runtimes and for policies that never
 * configured a cancellation stage, because an abandoned enrollment must always be
 * stoppable. It never invents workflow side effects: it reverses what this
 * enrollment itself applied (student synchronization, reserved class seats),
 * records the audited outcome, and leaves financial records for the finance team.
 */
final readonly class EnrollmentCancellationService
{
    public const string OUTCOME = 'cancelled';

    public function __construct(
        private EnrollmentPaymentService $payments,
        private EnrollmentStudentSynchronizationService $studentSynchronization,
    ) {}

    public function cancel(
        StudentEnrollment $enrollment,
        User $actor,
        string $reason,
        string $idempotencyKey,
        bool $acknowledgeOutstandingPayments = false,
    ): TransitionResult {
        if (! $actor->can('Cancel:StudentEnrollment') && ! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('You are not allowed to cancel enrollments.');
        }

        $reason = mb_trim($reason);
        if ($reason === '') {
            throw new EnrollmentTransitionException('A reason is required to cancel an enrollment.');
        }

        $scopedIdempotencyKey = $this->scopedIdempotencyKey($enrollment, $idempotencyKey);
        $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedIdempotencyKey)->first();
        if ($existing instanceof EnrollmentWorkflowEvent) {
            return $this->resultFromEvent($existing);
        }

        try {
            return DB::transaction(function () use ($enrollment, $actor, $reason, $scopedIdempotencyKey, $acknowledgeOutstandingPayments): TransitionResult {
                $locked = StudentEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);

                $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedIdempotencyKey)->first();
                if ($existing instanceof EnrollmentWorkflowEvent) {
                    return $this->resultFromEvent($existing);
                }

                if ($locked->terminal_outcome === self::OUTCOME) {
                    return new TransitionResult(
                        successful: true,
                        fromStepKey: $locked->current_step_key,
                        toStepKey: null,
                        terminalOutcome: self::OUTCOME,
                        message: 'This enrollment is already cancelled.',
                    );
                }

                if ($locked->terminal_outcome !== null) {
                    throw new EnrollmentTransitionException(
                        'Reopen this enrollment before cancelling it so every reversal stays auditable.',
                    );
                }

                $outstandingPayments = $this->outstandingPayments($locked);
                if ($outstandingPayments['transactions'] > 0 && ! $acknowledgeOutstandingPayments) {
                    throw new EnrollmentTransitionException(sprintf(
                        'This enrollment still holds %s in recorded payment across %d transaction(s). Reverse or refund them first, or confirm that the cancellation leaves them in place.',
                        number_format($outstandingPayments['amount'], 2),
                        $outstandingPayments['transactions'],
                    ));
                }

                $cancelledStage = $this->cancelledStage($locked);
                $fromStepKey = $locked->current_step_key;
                $fromStatus = $locked->status;

                $studentStateReversal = $this->studentSynchronization->reverseAppliedSynchronizations($locked);
                $releasedSeats = $this->releaseReservedSeats($locked);

                $updates = ['terminal_outcome' => self::OUTCOME];
                if (is_array($cancelledStage)) {
                    // A policy that defines a cancellation stage keeps reporting under the
                    // vocabulary its author chose; otherwise the last real stage status stays.
                    $updates['current_step_key'] = (string) $cancelledStage['key'];
                    if (($cancelledStage['status'] ?? null) !== null) {
                        $updates['status'] = (string) $cancelledStage['status'];
                    }
                }
                $locked->forceFill($updates)->save();

                EnrollmentWorkflowEvent::query()->create([
                    'student_enrollment_id' => $locked->id,
                    'enrollment_policy_snapshot_id' => $locked->enrollment_policy_snapshot_id,
                    'actor_id' => $actor->id,
                    'event_type' => 'cancelled',
                    'from_step_key' => $fromStepKey,
                    'to_step_key' => $updates['current_step_key'] ?? null,
                    'status' => $locked->status,
                    'terminal_outcome' => self::OUTCOME,
                    'idempotency_key' => $scopedIdempotencyKey,
                    'reason' => $reason,
                    'result' => [
                        'from_status' => $fromStatus,
                        'class_seats_released' => $releasedSeats['released'],
                        'class_seats_retained' => $releasedSeats['retained'],
                        'student_state_reversal' => $studentStateReversal,
                        'outstanding_payments' => $outstandingPayments,
                    ],
                ]);

                return new TransitionResult(
                    successful: true,
                    fromStepKey: $fromStepKey,
                    toStepKey: $updates['current_step_key'] ?? null,
                    terminalOutcome: self::OUTCOME,
                    message: 'Enrollment cancelled.',
                );
            }, 3);
        } catch (Throwable $exception) {
            EnrollmentWorkflowEvent::query()->firstOrCreate(
                ['idempotency_key' => $scopedIdempotencyKey],
                [
                    'student_enrollment_id' => $enrollment->id,
                    'enrollment_policy_snapshot_id' => $enrollment->enrollment_policy_snapshot_id,
                    'actor_id' => $actor->id,
                    'event_type' => 'cancel_failed',
                    'from_step_key' => $enrollment->current_step_key,
                    'status' => $enrollment->status,
                    'terminal_outcome' => $enrollment->terminal_outcome,
                    'reason' => $exception->getMessage(),
                    'result' => ['exception' => $exception::class],
                ],
            );

            throw $exception;
        }
    }

    /** @return array{transactions: int, amount: float} */
    public function outstandingPayments(StudentEnrollment $enrollment): array
    {
        return $this->payments->linkedSummary($enrollment);
    }

    /** @return array<string, mixed>|null */
    private function cancelledStage(StudentEnrollment $enrollment): ?array
    {
        if ($enrollment->workflow_runtime !== StudentEnrollment::WorkflowRuntimePolicyV1) {
            return null;
        }

        $enrollment->loadMissing('policySnapshot');
        $stage = collect(data_get($enrollment->policySnapshot?->configuration, 'workflow.steps', []))
            ->first(fn (mixed $step): bool => is_array($step)
                && ($step['terminal'] ?? false) === true
                && ($step['outcome'] ?? null) === self::OUTCOME);

        return is_array($stage) ? $stage : null;
    }

    /**
     * Release the class seats this enrollment reserved. Seats are deactivated instead of
     * deleted, and the subject rows keep their audit trail with the class link cleared so
     * a later reopen can assign classes again.
     *
     * A `class_enrollments` row is unique per student and class, so a seat another
     * enrollment also points at is left alone: it belongs to both records and releasing it
     * would unenrol a student the surviving enrollment still needs.
     *
     * @return array{released: array<int, array<string, mixed>>, retained: array<int, array<string, mixed>>}
     */
    private function releaseReservedSeats(StudentEnrollment $enrollment): array
    {
        $subjects = SubjectEnrollment::query()
            ->where('enrollment_id', $enrollment->id)
            ->whereNotNull('class_id')
            ->lockForUpdate()
            ->get();

        $released = [];
        $retained = [];
        foreach ($subjects as $subject) {
            $class = Classes::query()->find($subject->class_id);
            if (! $class instanceof Classes
                || $class->school_year !== $enrollment->school_year
                || (int) $class->semester !== (int) $enrollment->semester) {
                continue;
            }

            $seat = ClassEnrollment::query()
                ->where('student_id', $enrollment->student_id)
                ->where('class_id', $class->id)
                ->where('status', true)
                ->lockForUpdate()
                ->first();
            if (! $seat instanceof ClassEnrollment) {
                continue;
            }

            if ($this->classIsReferencedByAnotherEnrollment($enrollment, (int) $class->id)) {
                $retained[] = [
                    'class_enrollment_id' => $seat->id,
                    'class_id' => (int) $class->id,
                    'subject_enrollment_id' => $subject->id,
                    'reason' => 'another enrollment still references this class seat',
                ];

                continue;
            }

            $seat->forceFill(['status' => false])->save();
            $subject->forceFill(['class_id' => null])->save();

            $released[] = [
                'class_enrollment_id' => $seat->id,
                'class_id' => (int) $class->id,
                'subject_enrollment_id' => $subject->id,
            ];
        }

        return ['released' => $released, 'retained' => $retained];
    }

    private function classIsReferencedByAnotherEnrollment(StudentEnrollment $enrollment, int $classId): bool
    {
        return SubjectEnrollment::query()
            ->where('class_id', $classId)
            ->where('enrollment_id', '!=', $enrollment->id)
            ->exists();
    }

    private function resultFromEvent(EnrollmentWorkflowEvent $event): TransitionResult
    {
        return new TransitionResult(
            successful: $event->event_type === 'cancelled',
            fromStepKey: $event->from_step_key,
            toStepKey: $event->to_step_key,
            terminalOutcome: $event->terminal_outcome,
            actions: [],
            message: 'This cancellation attempt was already processed.',
        );
    }

    private function scopedIdempotencyKey(StudentEnrollment $enrollment, string $idempotencyKey): string
    {
        return hash('sha256', "cancel:{$enrollment->getKey()}:{$idempotencyKey}");
    }
}
