<?php

declare(strict_types=1);

namespace App\Data\Enrollment;

use App\Models\User;
use Illuminate\Support\Str;

final readonly class EnrollmentSubmissionData
{
    /**
     * @param  array<string, mixed>  $enrollmentAttributes
     * @param  array<int, array<string, mixed>>  $subjects
     * @param  array<int, array<string, mixed>>  $classAssignments
     * @param  array<int, array<string, mixed>>  $additionalFees
     * @param  array<string, mixed>  $billingOverrides
     * @param  array<string, mixed>  $facts
     * @param  array<string, array<string, mixed>>  $actionPayloads
     * @param  array<string, array<string, mixed>>  $requirementEvidence
     */
    public function __construct(
        public array $enrollmentAttributes,
        public array $subjects = [],
        public array $classAssignments = [],
        public array $additionalFees = [],
        public array $billingOverrides = [],
        public string $channel = 'administrator',
        public string $idempotencyKey = '',
        public ?User $actor = null,
        public array $facts = [],
        public array $actionPayloads = [],
        public array $requirementEvidence = [],
    ) {}

    /** @param array<string, mixed> $validated */
    public static function fromArray(
        array $validated,
        string $channel,
        ?User $actor = null,
        ?string $idempotencyKey = null,
    ): self {
        $attributes = $validated['enrollment'] ?? $validated;
        foreach ([
            'subjects',
            'subjectsEnrolled',
            'class_assignments',
            'classes',
            'additional_fees',
            'additionalFees',
            'billing',
            'billing_overrides',
            'action_payloads',
            'requirement_evidence',
            'idempotency_key',
        ] as $key) {
            unset($attributes[$key]);
        }

        return new self(
            enrollmentAttributes: $attributes,
            subjects: array_values($validated['subjects'] ?? $validated['subjectsEnrolled'] ?? []),
            classAssignments: array_values($validated['class_assignments'] ?? $validated['classes'] ?? []),
            additionalFees: array_values($validated['additional_fees'] ?? $validated['additionalFees'] ?? []),
            billingOverrides: $validated['billing_overrides'] ?? $validated['billing'] ?? [],
            channel: $channel,
            idempotencyKey: $idempotencyKey ?? (string) ($validated['idempotency_key'] ?? Str::uuid()),
            actor: $actor,
            facts: $validated['facts'] ?? [],
            actionPayloads: $validated['action_payloads'] ?? [],
            requirementEvidence: $validated['requirement_evidence'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function payloadForAction(string $actionKey, string $handler): array
    {
        if (isset($this->actionPayloads[$actionKey])) {
            return $this->actionPayloads[$actionKey];
        }

        return match ($handler) {
            'enrollment.assign_subjects' => ['subjects' => $this->subjects],
            'enrollment.assign_classes' => ['assignments' => $this->classAssignments],
            'enrollment.assign_additional_fees' => ['fees' => $this->additionalFees],
            'enrollment.calculate_tuition' => $this->billingOverrides,
            default => [],
        };
    }
}
