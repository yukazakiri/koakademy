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

final readonly class EnrollmentWorkflowCoordinator
{
    public function __construct(
        private EnrollmentTransitionEngine $engine,
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

        $nextStep = $this->legacyPipeline->getNextStep($enrollment->status);
        if ($nextStep === null) {
            throw new EnrollmentTransitionException('No next legacy enrollment step is available.');
        }
        if (! $this->legacyPipeline->canUserPerformStep($actor, $nextStep)) {
            throw new EnrollmentTransitionException('You are not allowed to complete this enrollment step.');
        }

        $from = $enrollment->status;
        $successful = match ($nextStep['action_type'] ?? 'standard') {
            'department_verification' => $this->legacyEnrollmentService->verifyByHeadDept($enrollment),
            'cashier_verification' => ($payload['without_receipt'] ?? false)
                ? $this->legacyEnrollmentService->verifyByCashierWithoutReceipt($enrollment, $payload)
                : $this->legacyEnrollmentService->verifyByCashier($enrollment, $payload),
            default => $enrollment->forceFill(['status' => $nextStep['status']])->save(),
        };

        if (! $successful) {
            throw new EnrollmentTransitionException('The legacy enrollment step could not be completed.');
        }

        return new TransitionResult(true, $from, (string) $nextStep['key'], null, message: 'Enrollment advanced.');
    }

    public function verifyAcademic(StudentEnrollment $enrollment, User $actor, ?string $idempotencyKey = null): TransitionResult
    {
        return $this->transitionByAction($enrollment, $actor, 'enrollment.verify_academic', [], $idempotencyKey);
    }

    /** @param array<string, mixed> $payload */
    public function verifyPayment(StudentEnrollment $enrollment, User $actor, array $payload, ?string $idempotencyKey = null): TransitionResult
    {
        return $this->transitionByAction($enrollment, $actor, 'enrollment.verify_payment', $payload, $idempotencyKey);
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

        $successful = $this->legacyPipeline->isCashierVerified($enrollment->status)
            ? $this->legacyEnrollmentService->undoCashierVerification((int) $enrollment->id)
            : $this->legacyEnrollmentService->undoHeadDeptVerification($enrollment);
        if (! $successful) {
            throw new EnrollmentTransitionException('The legacy enrollment could not be reopened.');
        }

        return new TransitionResult(true, null, null, null, message: 'Enrollment reopened.');
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

    /** @param array<string, mixed> $payload */
    private function transitionByAction(StudentEnrollment $enrollment, User $actor, string $handler, array $payload, ?string $idempotencyKey): TransitionResult
    {
        if ($enrollment->workflow_runtime !== StudentEnrollment::WorkflowRuntimePolicyV1) {
            return $this->transition($enrollment, $actor, null, $payload, $idempotencyKey);
        }

        $enrollment->loadMissing('policySnapshot');
        $steps = collect(data_get($enrollment->policySnapshot?->configuration, 'workflow.steps', []))->keyBy('key');
        $current = $steps->get($enrollment->current_step_key);
        if (! is_array($current)) {
            throw new EnrollmentTransitionException('The pinned workflow step is unavailable.');
        }
        if (($current['terminal'] ?? false) === true && $idempotencyKey !== null) {
            return $this->engine->transition($enrollment, $actor, null, [], $idempotencyKey);
        }

        $transition = collect($current['transitions'] ?? [])->first(function (array $transition) use ($steps, $handler): bool {
            $target = $steps->get($transition['to'] ?? '');

            return is_array($target) && collect($target['actions'] ?? [])->contains(fn (array $action): bool => ($action['handler'] ?? null) === $handler);
        });
        if (! is_array($transition)) {
            throw new EnrollmentTransitionException('The configured workflow has no matching verification transition.');
        }

        $target = $steps->get($transition['to']);
        $actionPayloads = collect($target['actions'] ?? [])
            ->filter(fn (array $action): bool => ($action['handler'] ?? null) === $handler)
            ->mapWithKeys(fn (array $action, int $index): array => [(string) ($action['key'] ?? $index) => $payload])
            ->all();
        if (isset($payload['reason'])) {
            $actionPayloads['reason'] = $payload['reason'];
        }

        return $this->engine->transition($enrollment, $actor, $transition['key'] ?? null, $actionPayloads, $idempotencyKey ?? (string) Str::uuid());
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
