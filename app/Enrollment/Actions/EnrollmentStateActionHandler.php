<?php

declare(strict_types=1);

namespace App\Enrollment\Actions;

use App\Contracts\Enrollment\EnrollmentActionHandler;
use App\Contracts\Enrollment\EnrollmentOperatorSchemaProvider;
use App\Data\Enrollment\ActionResult;
use App\Data\Enrollment\EnrollmentContext;
use App\Enrollment\EnrollmentStudentSynchronizationService;

final readonly class EnrollmentStateActionHandler implements EnrollmentActionHandler, EnrollmentOperatorSchemaProvider
{
    public function __construct(
        private string $handlerKey,
        private string $label,
        private EnrollmentStudentSynchronizationService $studentSynchronization,
    ) {}

    public function key(): string
    {
        return $this->handlerKey;
    }

    public function metadata(): array
    {
        return ['key' => $this->handlerKey, 'label' => $this->label, 'category' => 'workflow'];
    }

    public function payloadSchema(): array
    {
        return ['type' => 'object'];
    }

    public function operatorSchema(): array
    {
        return [
            'description' => match ($this->handlerKey) {
                'enrollment.change_status' => 'Update the legacy reporting status for integrations and reports.',
                'enrollment.set_outcome' => 'Mark the enrollment as completed, rejected, or cancelled.',
                'enrollment.sync_student' => 'Synchronize the student or linked account after enrollment.',
                default => 'Update enrollment state.',
            },
            'fields' => match ($this->handlerKey) {
                'enrollment.change_status' => [[
                    'key' => 'status', 'label' => 'Reporting status', 'control' => 'text', 'required' => true,
                ]],
                'enrollment.set_outcome' => [[
                    'key' => 'outcome', 'label' => 'Final outcome', 'control' => 'select', 'required' => true,
                    'options' => [
                        ['value' => 'completed', 'label' => 'Completed'],
                        ['value' => 'rejected', 'label' => 'Rejected'],
                        ['value' => 'cancelled', 'label' => 'Cancelled'],
                    ],
                ]],
                'enrollment.sync_student' => [
                    ['key' => 'attribute', 'label' => 'Student field', 'control' => 'select', 'required' => true, 'options' => [
                        ['value' => 'status', 'label' => 'Status'],
                        ['value' => 'student_status', 'label' => 'Student status'],
                    ]],
                    ['key' => 'value', 'label' => 'Value', 'control' => 'text', 'required' => true],
                ],
                default => [],
            },
        ];
    }

    public function execute(EnrollmentContext $context, array $configuration, string $idempotencyKey): ActionResult
    {
        $enrollment = $context->enrollment;
        if (! $enrollment instanceof \App\Models\StudentEnrollment) {
            return ActionResult::failure('This action requires a persisted enrollment.');
        }

        return match ($this->handlerKey) {
            'enrollment.change_status' => $this->changeStatus($context, $configuration),
            'enrollment.set_outcome' => $this->setOutcome($context, $configuration),
            'enrollment.sync_student' => $this->syncStudent($context, $configuration),
            default => ActionResult::failure("No executor is configured for [{$this->handlerKey}]."),
        };
    }

    /** @param array<string, mixed> $configuration */
    private function changeStatus(EnrollmentContext $context, array $configuration): ActionResult
    {
        $status = mb_trim((string) ($configuration['status'] ?? ''));
        if ($status === '') {
            return ActionResult::failure('A compatibility status is required.');
        }

        return ActionResult::success(['compatibility_status' => $status, 'owned_by_engine' => true]);
    }

    /** @param array<string, mixed> $configuration */
    private function setOutcome(EnrollmentContext $context, array $configuration): ActionResult
    {
        $outcome = (string) ($configuration['outcome'] ?? 'completed');
        if (! in_array($outcome, ['completed', 'rejected', 'cancelled'], true)) {
            return ActionResult::failure('Terminal outcome must be completed, rejected, or cancelled.');
        }

        return ActionResult::success(['terminal_outcome' => $outcome, 'owned_by_engine' => true]);
    }

    /** @param array<string, mixed> $configuration */
    private function syncStudent(EnrollmentContext $context, array $configuration): ActionResult
    {
        return $this->studentSynchronization->synchronize($context, $configuration);
    }
}
