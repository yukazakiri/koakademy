<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Data\Enrollment\TransitionResult;
use App\Enrollment\Exceptions\EnrollmentTransitionException;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class EnrollmentTransitionEngine
{
    public function __construct(
        private EnrollmentPolicyResolver $resolver,
        private EnrollmentPolicyRegistry $registry,
        private EnrollmentRequirementService $requirements,
        private EnrollmentPaymentService $payments,
        private EnrollmentActionPayloadValidator $payloadValidator,
        private EnrollmentStudentSynchronizationService $studentSynchronization,
    ) {}

    public function initialize(StudentEnrollment $enrollment, EnrollmentSubmissionData $submission): TransitionResult
    {
        $idempotencyKey = $this->scopedIdempotencyKey('initialize', $enrollment, $submission->idempotencyKey);
        $this->ensurePolicyRuntime($enrollment);

        return DB::transaction(function () use ($enrollment, $submission, $idempotencyKey): TransitionResult {
            $locked = StudentEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $this->ensurePolicyRuntime($locked);

            $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return new TransitionResult(
                    true,
                    null,
                    $existing->to_step_key,
                    $existing->terminal_outcome,
                    $existing->result['actions'] ?? [],
                    'This enrollment initialization was already processed.',
                );
            }

            $snapshot = $locked->policySnapshot()->firstOrFail();
            $entry = collect(data_get($snapshot->configuration, 'workflow.steps', []))->firstWhere('entry', true);
            if (! is_array($entry)) {
                throw new EnrollmentTransitionException('The pinned enrollment policy has no entry step.');
            }

            $this->requirements->materialize($locked, $snapshot, $submission->requirementEvidence);
            $context = EnrollmentContext::fromEnrollment(
                $locked,
                $submission->channel,
                $submission->actor,
                $submission->facts,
            );
            $actionPayloads = [];
            foreach ($entry['actions'] ?? [] as $index => $action) {
                $actionPayloads[(string) ($action['key'] ?? $index)] = $submission->payloadForAction(
                    (string) ($action['key'] ?? $index),
                    (string) ($action['handler'] ?? ''),
                );
            }
            $actionResults = $this->executeActions($entry['actions'] ?? [], $context, $actionPayloads, $idempotencyKey);

            $locked->forceFill([
                'current_step_key' => (string) $entry['key'],
                'status' => (string) ($entry['status'] ?? $locked->status),
                'terminal_outcome' => ($entry['terminal'] ?? false) ? ($entry['outcome'] ?? null) : null,
            ])->save();

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $locked->id,
                'enrollment_policy_snapshot_id' => $snapshot->id,
                'actor_id' => $submission->actor?->id,
                'event_type' => 'initialized',
                'to_step_key' => $entry['key'],
                'status' => $locked->status,
                'terminal_outcome' => $locked->terminal_outcome,
                'idempotency_key' => $idempotencyKey,
                'result' => ['channel' => $submission->channel, 'actions' => $actionResults],
            ]);

            return new TransitionResult(true, null, (string) $entry['key'], $locked->terminal_outcome, $actionResults);
        }, 3);
    }

    /** @param array<string, mixed> $payload */
    public function transition(StudentEnrollment $enrollment, User $actor, ?string $transitionKey, array $payload, string $idempotencyKey): TransitionResult
    {
        $idempotencyKey = $this->scopedIdempotencyKey('transition', $enrollment, $idempotencyKey);
        $this->ensurePolicyRuntime($enrollment);
        $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return new TransitionResult(
                successful: $existing->event_type === 'transition_succeeded',
                fromStepKey: $existing->from_step_key,
                toStepKey: $existing->to_step_key,
                terminalOutcome: $existing->terminal_outcome,
                actions: $existing->result['actions'] ?? [],
                message: 'This transition attempt was already processed.',
            );
        }

        try {
            return DB::transaction(function () use ($enrollment, $actor, $transitionKey, $payload, $idempotencyKey): TransitionResult {
                $locked = StudentEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
                $this->ensurePolicyRuntime($locked);
                $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $idempotencyKey)->first();
                if ($existing) {
                    return new TransitionResult(
                        $existing->event_type === 'transition_succeeded',
                        $existing->from_step_key,
                        $existing->to_step_key,
                        $existing->terminal_outcome,
                        $existing->result['actions'] ?? [],
                        'This transition attempt was already processed.',
                    );
                }
                if (! $locked->enrollment_policy_snapshot_id) {
                    $context = EnrollmentContext::fromEnrollment($locked, actor: $actor);
                    $snapshot = $this->resolver->snapshot($context);
                    $locked->enrollment_policy_snapshot_id = $snapshot->id;
                } else {
                    $snapshot = $locked->policySnapshot()->firstOrFail();
                }
                $context = EnrollmentContext::fromEnrollment($locked, actor: $actor);

                $steps = collect(data_get($snapshot->configuration, 'workflow.steps', []))->keyBy('key');
                $current = $locked->current_step_key
                    ? $steps->get($locked->current_step_key)
                    : $steps->firstWhere('entry', true);

                if (! is_array($current)) {
                    throw new EnrollmentTransitionException('The enrollment has no valid current workflow step.');
                }
                if (($current['terminal'] ?? false) === true) {
                    throw new EnrollmentTransitionException('Terminal enrollments must be reopened before another transition.');
                }

                $permission = $current['permission'] ?? null;
                if (is_string($permission) && $permission !== '' && ! $actor->can($permission)) {
                    throw new AuthorizationException('You are not allowed to transition this enrollment step.');
                }

                $selected = $this->selectTransition($current['transitions'] ?? [], $context, $transitionKey);
                $target = $steps->get($selected['to']);
                if (! is_array($target)) {
                    throw new EnrollmentTransitionException('The selected transition target is unavailable.');
                }

                $reason = mb_trim((string) ($payload['reason'] ?? ''));
                if (($selected['requires_reason'] ?? false) === true && $reason === '') {
                    throw new EnrollmentTransitionException('A reason is required for this enrollment transition.');
                }

                $this->requirements->assertSatisfiedForStep($locked, (string) $target['key']);
                $actionResults = $this->executeActions($target['actions'] ?? [], $context, $payload, $idempotencyKey);
                if (($target['terminal'] ?? false) === true) {
                    $this->assertCompletionRules($snapshot->configuration['rules'] ?? [], $context);
                }

                $notifications = is_array($snapshot->configuration['notifications'] ?? null)
                    ? $snapshot->configuration['notifications']
                    : [];
                foreach ($notifications as $index => $notification) {
                    if (! is_array($notification)) {
                        continue;
                    }
                    if (($notification['enabled'] ?? true) === false) {
                        continue;
                    }
                    if (! in_array($notification['event'] ?? null, ['any_transition', $target['key'], $target['outcome'] ?? null], true)) {
                        continue;
                    }
                    if (($notification['channel'] ?? 'mail') !== 'mail') {
                        throw new EnrollmentTransitionException('The core notification action currently supports email only.');
                    }

                    $result = $this->registry->action('enrollment.notify')->execute(
                        $context,
                        ['notification' => 'assessment'],
                        "{$idempotencyKey}:notification:{$index}",
                    );
                    $actionResults[] = ['key' => 'enrollment.notify', 'successful' => $result->successful, 'message' => $result->message, 'metadata' => $result->metadata];
                    if (! $result->successful) {
                        throw new EnrollmentTransitionException($result->message);
                    }
                }

                $locked->current_step_key = (string) $target['key'];
                $locked->status = (string) ($target['status'] ?? $locked->status);
                $locked->terminal_outcome = ($target['terminal'] ?? false) ? ($target['outcome'] ?? $locked->terminal_outcome) : null;
                $locked->save();

                EnrollmentWorkflowEvent::query()->create([
                    'student_enrollment_id' => $locked->id,
                    'enrollment_policy_snapshot_id' => $snapshot->id,
                    'actor_id' => $actor->id,
                    'event_type' => 'transition_succeeded',
                    'from_step_key' => $current['key'],
                    'to_step_key' => $target['key'],
                    'status' => $locked->status,
                    'terminal_outcome' => $locked->terminal_outcome,
                    'idempotency_key' => $idempotencyKey,
                    'reason' => $reason === '' ? null : $reason,
                    'result' => ['transition_key' => $selected['key'] ?? null, 'actions' => $actionResults],
                ]);

                return new TransitionResult(true, $current['key'], $target['key'], $locked->terminal_outcome, $actionResults);
            }, 3);
        } catch (Throwable $exception) {
            EnrollmentWorkflowEvent::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey],
                [
                    'student_enrollment_id' => $enrollment->id,
                    'enrollment_policy_snapshot_id' => $enrollment->enrollment_policy_snapshot_id,
                    'actor_id' => $actor->id,
                    'event_type' => 'transition_failed',
                    'from_step_key' => $enrollment->current_step_key,
                    'reason' => $exception->getMessage(),
                    'result' => ['exception' => $exception::class],
                ],
            );

            throw $exception;
        }
    }

    public function reopen(
        StudentEnrollment $enrollment,
        User $actor,
        ?string $targetStepKey,
        string $reason,
        string $idempotencyKey,
    ): TransitionResult {
        $idempotencyKey = $this->scopedIdempotencyKey('reopen', $enrollment, $idempotencyKey);
        $this->ensurePolicyRuntime($enrollment);
        if (! $actor->can('Reopen:StudentEnrollment') && ! $actor->hasRole('super_admin')) {
            throw new AuthorizationException('You are not allowed to reopen enrollment workflows.');
        }

        $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return new TransitionResult(true, $existing->from_step_key, $existing->to_step_key, $existing->terminal_outcome, message: 'This reopen attempt was already processed.');
        }

        return DB::transaction(function () use ($enrollment, $actor, $targetStepKey, $reason, $idempotencyKey): TransitionResult {
            $locked = StudentEnrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $this->ensurePolicyRuntime($locked);
            $existing = EnrollmentWorkflowEvent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return new TransitionResult(true, $existing->from_step_key, $existing->to_step_key, $existing->terminal_outcome, message: 'This reopen attempt was already processed.');
            }
            if ($locked->terminal_outcome === null) {
                throw new EnrollmentTransitionException('Only a terminal enrollment can be reopened.');
            }

            $snapshot = $locked->policySnapshot()->firstOrFail();
            $steps = collect(data_get($snapshot->configuration, 'workflow.steps', []));
            $target = $targetStepKey === null
                ? $steps->firstWhere('entry', true)
                : $steps->firstWhere('key', $targetStepKey);
            if (! is_array($target) || ($target['terminal'] ?? false) === true) {
                throw new EnrollmentTransitionException('Reopen target must be a non-terminal step from the pinned snapshot.');
            }

            $fromStepKey = $locked->current_step_key;
            $studentStateReversal = $this->reverseStudentSynchronization($locked, (string) $fromStepKey);
            $reversal = $this->payments->reverseLinked($locked);
            $locked->update([
                'current_step_key' => $target['key'],
                'terminal_outcome' => null,
                'status' => $target['status'] ?? $locked->status,
            ]);
            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $locked->id,
                'enrollment_policy_snapshot_id' => $snapshot->id,
                'actor_id' => $actor->id,
                'event_type' => 'reopened',
                'from_step_key' => $fromStepKey,
                'to_step_key' => $target['key'],
                'status' => $locked->status,
                'idempotency_key' => $idempotencyKey,
                'reason' => $reason,
                'result' => [
                    'payment_reversal' => $reversal,
                    'student_state_reversal' => $studentStateReversal,
                ],
            ]);

            return new TransitionResult(true, $fromStepKey, $target['key'], null, message: 'Enrollment reopened.');
        }, 3);
    }

    /** @return array<string, mixed> */
    private function reverseStudentSynchronization(StudentEnrollment $enrollment, string $terminalStepKey): array
    {
        $terminalEvent = EnrollmentWorkflowEvent::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->where('event_type', 'transition_succeeded')
            ->where('to_step_key', $terminalStepKey)
            ->latest('id')
            ->first();
        $actions = is_array($terminalEvent?->result['actions'] ?? null)
            ? $terminalEvent->result['actions']
            : [];
        $synchronizations = collect($actions)
            ->filter(fn (mixed $action): bool => is_array($action) && ($action['key'] ?? null) === 'enrollment.sync_student')
            ->reverse()
            ->values();
        if ($synchronizations->isEmpty()) {
            return ['restored' => false, 'skipped' => true];
        }

        $reversals = [];
        foreach ($synchronizations as $synchronization) {
            $metadata = $synchronization['metadata'] ?? null;
            if (! is_array($metadata)) {
                throw new EnrollmentTransitionException('Student synchronization cannot be safely reversed because its audit snapshot is unavailable.');
            }
            $reversals[] = $this->studentSynchronization->reverse($enrollment, $metadata);
        }

        return ['restored' => true, 'actions' => $reversals];
    }

    /** @param array<int, array<string, mixed>> $transitions @return array<string, mixed> */
    private function selectTransition(array $transitions, EnrollmentContext $context, ?string $requestedKey): array
    {
        foreach ($transitions as $transition) {
            if ($requestedKey !== null && ($transition['key'] ?? null) !== $requestedKey) {
                continue;
            }

            if (($transition['fallback'] ?? false) === true || $this->conditionsPass($transition['conditions'] ?? [], $context)) {
                return $transition;
            }
        }

        throw new EnrollmentTransitionException('No transition is available for the current enrollment context.');
    }

    /** @param array<int, array<string, mixed>> $rules */
    private function assertCompletionRules(array $rules, EnrollmentContext $context): void
    {
        foreach ($rules as $rule) {
            $handler = (string) ($rule['handler'] ?? '');
            if (! EnrollmentRuleTiming::appliesAtCompletion($handler)) {
                continue;
            }

            $result = $this->registry->rule($handler)->evaluate($context, $rule['configuration'] ?? []);
            if (! $result->passed) {
                throw new EnrollmentTransitionException($result->message);
            }
        }
    }

    /** @param array<int, array<string, mixed>> $conditions */
    private function conditionsPass(array $conditions, EnrollmentContext $context): bool
    {
        foreach ($conditions as $condition) {
            $result = $this->registry->rule((string) $condition['handler'])
                ->evaluate($context, $condition['configuration'] ?? []);
            if (! $result->passed) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function executeActions(
        array $actions,
        EnrollmentContext $context,
        array $payload,
        string $idempotencyKey,
    ): array {
        $results = [];

        foreach ($actions as $index => $action) {
            $actionKey = (string) ($action['handler'] ?? '');
            $handler = $this->registry->action($actionKey);
            $runtimePayload = $payload[$action['key'] ?? $index] ?? [];
            $this->payloadValidator->validate(
                $runtimePayload,
                $handler->payloadSchema(),
                (string) ($action['key'] ?? $index),
            );
            $result = $handler->execute(
                $context,
                [
                    ...($action['configuration'] ?? []),
                    'runtime_payload' => $runtimePayload,
                ],
                "{$idempotencyKey}:{$index}",
            );
            $results[] = [
                'key' => $actionKey,
                'successful' => $result->successful,
                'message' => $result->message,
                'metadata' => $result->metadata,
            ];

            if (! $result->successful) {
                throw new EnrollmentTransitionException($result->message);
            }
        }

        return $results;
    }

    private function ensurePolicyRuntime(StudentEnrollment $enrollment): void
    {
        if ($enrollment->workflow_runtime !== StudentEnrollment::WorkflowRuntimePolicyV1) {
            throw new EnrollmentTransitionException('Legacy enrollments must use the legacy compatibility workflow.');
        }
    }

    private function scopedIdempotencyKey(string $operation, StudentEnrollment $enrollment, string $key): string
    {
        return hash('sha256', "{$operation}:{$enrollment->getKey()}:{$key}");
    }
}
