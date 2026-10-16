<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Data\Enrollment\TransitionResult;
use App\Enrollment\Exceptions\EnrollmentTransitionException;
use App\Models\EnrollmentRequirement;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\EnrollmentPipelineService;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class EnrollmentWorkflowCoordinator
{
    public function __construct(
        private EnrollmentTransitionEngine $engine,
        private EnrollmentCancellationService $cancellation,
        private LegacyEnrollmentWorkflowAdapter $legacyEnrollmentService,
        private EnrollmentPipelineService $legacyPipeline,
        private EnrollmentSubmissionContext $submissionContext,
        private EnrollmentRequirementService $requirements,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes, ?Closure $afterCreate = null): StudentEnrollment
    {
        $actor = auth()->user();
        $submission = new EnrollmentSubmissionData(
            enrollmentAttributes: $attributes,
            channel: $this->requestChannel(),
            idempotencyKey: (string) Str::uuid(),
            actor: $actor instanceof User ? $actor : null,
        );

        return $this->submit($submission, $afterCreate);
    }

    public function submit(EnrollmentSubmissionData $submission, ?Closure $legacyAfterCreate = null): StudentEnrollment
    {
        $submissionKey = hash('sha256', $submission->channel.':'.(
            $submission->idempotencyKey !== '' ? $submission->idempotencyKey : (string) Str::uuid()
        ));
        $existing = StudentEnrollment::query()
            ->where('submission_idempotency_key', $submissionKey)
            ->first();
        if ($existing instanceof StudentEnrollment) {
            return $existing;
        }

        try {
            return $this->submissionContext->run($submission, fn (): StudentEnrollment => DB::transaction(
                function () use ($submission, $legacyAfterCreate, $submissionKey): StudentEnrollment {
                    $enrollment = StudentEnrollment::query()->create([
                        ...$submission->enrollmentAttributes,
                        'submission_channel' => $submission->channel,
                        'submission_idempotency_key' => $submissionKey,
                    ]);

                    if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
                        $this->engine->initialize($enrollment, $submission);
                    } else {
                        $legacyAfterCreate?->__invoke($enrollment);
                    }

                    return $enrollment->refresh();
                },
                3,
            ));
        } catch (UniqueConstraintViolationException $exception) {
            $existing = StudentEnrollment::query()
                ->where('submission_idempotency_key', $submissionKey)
                ->first();

            return $existing ?? throw $exception;
        }
    }

    public function updateLegacyReportingStatus(StudentEnrollment $enrollment, string $status): void
    {
        if ($enrollment->workflow_runtime !== StudentEnrollment::WorkflowRuntimeLegacy) {
            throw new EnrollmentTransitionException('Policy enrollment statuses can only change through a workflow transition.');
        }

        $enrollment->forceFill(['status' => $status])->save();
    }

    /** @param array<string, mixed> $payload */
    public function transition(StudentEnrollment $enrollment, User $actor, ?string $transitionKey, array $payload = [], ?string $idempotencyKey = null): TransitionResult
    {
        if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
            return $this->engine->transition($enrollment, $actor, $transitionKey, $payload, $idempotencyKey ?? (string) Str::uuid());
        }

        $requestKey = $idempotencyKey ?? (string) Str::uuid();
        $scopedIdempotencyKey = hash('sha256', "legacy-transition:{$enrollment->id}:{$requestKey}");

        $existing = EnrollmentWorkflowEvent::query()
            ->where('idempotency_key', $scopedIdempotencyKey)
            ->first();

        if ($existing instanceof EnrollmentWorkflowEvent) {
            return $this->transitionResultFromEvent($existing);
        }

        try {
            return DB::transaction(function () use ($enrollment, $actor, $transitionKey, $payload, $scopedIdempotencyKey): TransitionResult {
                $locked = StudentEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
                $existing = EnrollmentWorkflowEvent::query()
                    ->where('idempotency_key', $scopedIdempotencyKey)
                    ->first();

                if ($existing instanceof EnrollmentWorkflowEvent) {
                    return $this->transitionResultFromEvent($existing);
                }

                if ($locked->terminal_outcome !== null) {
                    throw new EnrollmentTransitionException('Terminal enrollments must be reopened before another transition.');
                }

                $nextStep = $this->legacyPipeline->getNextStep($locked->status);
                if ($nextStep === null) {
                    throw new EnrollmentTransitionException('No next legacy enrollment step is available.');
                }
                if (! $this->legacyPipeline->canUserPerformStep($actor, $nextStep)) {
                    throw new EnrollmentTransitionException('You are not allowed to complete this enrollment step.');
                }

                $from = $locked->status;
                $successful = match ($nextStep['action_type'] ?? 'standard') {
                    'department_verification' => $this->legacyEnrollmentService->verifyByHeadDept($locked),
                    'cashier_verification' => ($payload['without_receipt'] ?? false)
                        ? $this->legacyEnrollmentService->verifyByCashierWithoutReceipt($locked, $payload)
                        : $this->legacyEnrollmentService->verifyByCashier($locked, $payload),
                    default => $locked->forceFill(['status' => $nextStep['status']])->save(),
                };

                if (! $successful) {
                    throw new EnrollmentTransitionException('The legacy enrollment step could not be completed.');
                }

                $updated = $locked->refresh();
                EnrollmentWorkflowEvent::query()->create([
                    'student_enrollment_id' => $updated->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'transition_succeeded',
                    'from_step_key' => $from,
                    'to_step_key' => (string) $nextStep['key'],
                    'status' => $updated->status,
                    'idempotency_key' => $scopedIdempotencyKey,
                    'reason' => isset($payload['reason']) ? (string) $payload['reason'] : null,
                    'result' => [
                        'transition_key' => $transitionKey,
                        'actions' => [],
                    ],
                ]);

                return new TransitionResult(true, $from, (string) $nextStep['key'], null, message: 'Enrollment advanced.');
            }, 3);
        } catch (Throwable $exception) {
            EnrollmentWorkflowEvent::query()->firstOrCreate(
                ['idempotency_key' => $scopedIdempotencyKey],
                [
                    'student_enrollment_id' => $enrollment->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'transition_failed',
                    'from_step_key' => $enrollment->status,
                    'reason' => $exception->getMessage(),
                    'result' => ['exception' => $exception::class],
                ],
            );

            throw $exception;
        }
    }

    public function verifyAcademic(StudentEnrollment $enrollment, User $actor, ?string $idempotencyKey = null): TransitionResult
    {
        return $this->transitionByAction($enrollment, $actor, 'enrollment.verify_academic', [], $idempotencyKey);
    }

    /** @param array<string, mixed> $payload */
    public function verifyPayment(StudentEnrollment $enrollment, User $actor, array $payload, ?string $idempotencyKey = null, ?string $transitionKey = null): TransitionResult
    {
        return $this->transitionByAction($enrollment, $actor, 'enrollment.verify_payment', $payload, $idempotencyKey, $transitionKey);
    }

    public function quickEnroll(
        StudentEnrollment $enrollment,
        User $actor,
        string $reason,
        ?string $idempotencyKey = null,
    ): TransitionResult {
        $idempotencyKey ??= (string) Str::uuid();
        if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimeLegacy
            && $this->legacyPipeline->isPending($enrollment->status)) {
            $this->verifyAcademic($enrollment, $actor, "{$idempotencyKey}:academic");
            $enrollment->refresh();
        }

        return $this->verifyPayment($enrollment, $actor, [
            'without_receipt' => true,
            'reason' => $reason,
            'remarks' => $reason,
        ], "{$idempotencyKey}:payment");
    }

    public function reopen(StudentEnrollment $enrollment, ?User $actor, ?string $targetStepKey, string $reason, ?string $idempotencyKey = null): TransitionResult
    {
        if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
            if (! $actor instanceof User) {
                throw new EnrollmentTransitionException('An authenticated actor is required to reopen a policy enrollment.');
            }

            return $this->engine->reopen($enrollment, $actor, $targetStepKey, $reason, $idempotencyKey ?? (string) Str::uuid());
        }

        // The legacy pipeline has no step graph to roll back to: a cancellation is undone by
        // restoring the status it interrupted, not by reverting a verification.
        if ($enrollment->terminal_outcome === EnrollmentCancellationService::OUTCOME) {
            return $this->undoLegacyCancellation($enrollment, $actor, $reason, $idempotencyKey ?? (string) Str::uuid());
        }

        $successful = $this->legacyPipeline->isCashierVerified($enrollment->status)
            ? $this->legacyEnrollmentService->undoCashierVerification((int) $enrollment->id)
            : $this->legacyEnrollmentService->undoHeadDeptVerification($enrollment);
        if (! $successful) {
            throw new EnrollmentTransitionException('The legacy enrollment could not be reopened.');
        }

        return new TransitionResult(true, null, null, null, message: 'Enrollment reopened.');
    }

    /**
     * Cancel an enrollment for either runtime.
     *
     * The legacy pipeline has no terminal outcome of its own, so a cashier-verified
     * legacy enrollment is refused here: its money and student state were applied by the
     * compatibility workflow and have to be undone through the existing undo path first.
     */
    public function cancel(
        StudentEnrollment $enrollment,
        User $actor,
        string $reason,
        ?string $idempotencyKey = null,
        bool $acknowledgeOutstandingPayments = false,
    ): TransitionResult {
        if ($enrollment->workflow_runtime !== StudentEnrollment::WorkflowRuntimePolicyV1
            && $this->legacyPipeline->isCashierVerified($enrollment->status)) {
            throw new EnrollmentTransitionException(
                'Undo the cashier verification for this enrollment before cancelling it so the reversal stays auditable.',
            );
        }

        return $this->cancellation->cancel(
            $enrollment,
            $actor,
            $reason,
            $idempotencyKey ?? (string) Str::uuid(),
            $acknowledgeOutstandingPayments,
        );
    }

    public function verifyRequirement(
        EnrollmentRequirement $requirement,
        User $actor,
        ?string $evidencePath = null,
        ?string $idempotencyKey = null,
    ): EnrollmentRequirement {
        return $this->mutateRequirement(
            $requirement,
            $actor,
            'requirement_verified',
            $idempotencyKey ?? (string) Str::uuid(),
            fn (EnrollmentRequirement $locked): EnrollmentRequirement => $this->requirements->verify($locked, $actor, $evidencePath),
        );
    }

    public function waiveRequirement(
        EnrollmentRequirement $requirement,
        User $actor,
        string $reason,
        ?string $idempotencyKey = null,
    ): EnrollmentRequirement {
        return $this->mutateRequirement(
            $requirement,
            $actor,
            'requirement_waived',
            $idempotencyKey ?? (string) Str::uuid(),
            fn (EnrollmentRequirement $locked): EnrollmentRequirement => $this->requirements->waive($locked, $actor, $reason),
            $reason,
        );
    }

    private function undoLegacyCancellation(
        StudentEnrollment $enrollment,
        ?User $actor,
        string $reason,
        string $idempotencyKey,
    ): TransitionResult {
        $scopedIdempotencyKey = hash('sha256', "legacy-cancel-reopen:{$enrollment->id}:{$idempotencyKey}");

        $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedIdempotencyKey)->first();
        if ($existing instanceof EnrollmentWorkflowEvent) {
            return new TransitionResult(
                successful: true,
                fromStepKey: $existing->from_step_key,
                toStepKey: $existing->to_step_key,
                terminalOutcome: $existing->terminal_outcome,
                message: 'This reopen attempt was already processed.',
            );
        }

        return DB::transaction(function () use ($enrollment, $actor, $reason, $scopedIdempotencyKey): TransitionResult {
            $locked = StudentEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            if ($locked->terminal_outcome !== EnrollmentCancellationService::OUTCOME) {
                throw new EnrollmentTransitionException('This enrollment is not cancelled.');
            }

            $cancellation = EnrollmentWorkflowEvent::query()
                ->where('student_enrollment_id', $locked->id)
                ->where('event_type', 'cancelled')
                ->latest('id')
                ->first();
            $restoredStatus = data_get($cancellation?->result, 'from_status');

            $locked->forceFill([
                'terminal_outcome' => null,
                'status' => is_string($restoredStatus) && $restoredStatus !== '' ? $restoredStatus : $locked->status,
            ])->save();

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $locked->id,
                'actor_id' => $actor?->id,
                'event_type' => 'reopened',
                'from_step_key' => $cancellation?->to_step_key,
                'status' => $locked->status,
                'idempotency_key' => $scopedIdempotencyKey,
                'reason' => $reason,
                'result' => [
                    'cancellation_event_id' => $cancellation?->id,
                    'restored_status' => $locked->status,
                    'payment_reversal' => [
                        'skipped' => true,
                        'reason' => 'Cancellation never reversed payments; reopening does not either.',
                    ],
                ],
            ]);

            return new TransitionResult(
                successful: true,
                fromStepKey: $cancellation?->to_step_key,
                toStepKey: null,
                terminalOutcome: null,
                message: 'Enrollment cancellation reversed.',
            );
        }, 3);
    }

    private function transitionResultFromEvent(EnrollmentWorkflowEvent $event): TransitionResult
    {
        return new TransitionResult(
            successful: $event->event_type === 'transition_succeeded',
            fromStepKey: $event->from_step_key,
            toStepKey: $event->to_step_key,
            terminalOutcome: $event->terminal_outcome,
            actions: $event->result['actions'] ?? [],
            message: 'This transition attempt was already processed.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function transitionByAction(StudentEnrollment $enrollment, User $actor, string $handler, array $payload, ?string $idempotencyKey, ?string $transitionKey = null): TransitionResult
    {
        if ($enrollment->workflow_runtime !== StudentEnrollment::WorkflowRuntimePolicyV1) {
            return $this->transition($enrollment, $actor, null, $payload, $idempotencyKey);
        }

        return $this->engine->transition(
            $enrollment, $actor, $transitionKey, $payload, $idempotencyKey ?? (string) Str::uuid(), $handler,
        );
    }

    private function mutateRequirement(
        EnrollmentRequirement $requirement,
        User $actor,
        string $eventType,
        string $idempotencyKey,
        Closure $mutation,
        ?string $reason = null,
    ): EnrollmentRequirement {
        if (! $actor->can('Update:StudentEnrollment') && ! $actor->hasRole('super_admin')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('You are not allowed to review enrollment requirements.');
        }

        $scopedKey = hash('sha256', "requirement:{$requirement->id}:{$idempotencyKey}");

        return DB::transaction(function () use ($requirement, $actor, $eventType, $scopedKey, $mutation, $reason): EnrollmentRequirement {
            $locked = EnrollmentRequirement::query()->lockForUpdate()->findOrFail($requirement->id);
            if (EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedKey)->exists()) {
                return $locked;
            }

            $updated = $mutation($locked);
            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $updated->student_enrollment_id,
                'enrollment_policy_snapshot_id' => $updated->enrollment_policy_snapshot_id,
                'actor_id' => $actor->id,
                'event_type' => $eventType,
                'idempotency_key' => $scopedKey,
                'reason' => $reason,
                'result' => ['requirement_key' => $updated->requirement_key, 'status' => $updated->status],
            ]);

            return $updated;
        }, 3);
    }

    private function requestChannel(): string
    {
        if (request()->routeIs('enrollment.continuing.*')) {
            return 'continuing';
        }
        if (request()->routeIs('enrollment.*')) {
            return 'public';
        }
        if (request()->is('api/*')) {
            return 'api';
        }

        return 'administrator';
    }
}
