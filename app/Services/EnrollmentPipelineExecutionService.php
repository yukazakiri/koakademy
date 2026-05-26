<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\StudentEnrollment;

final class EnrollmentPipelineExecutionService
{
    public function __construct(
        private readonly EnrollmentPipelineService $pipelineService,
        private readonly EnrollmentService $enrollmentService,
    ) {}

    public function executeStep(StudentEnrollment $studentEnrollment, array $step, array $payload = []): array
    {
        $conditions = $this->normalizeConditions($step);
        $actions = $this->normalizeActions($step);

        $conditionResults = [];
        foreach ($conditions as $condition) {
            $result = $this->evaluateCondition($studentEnrollment, $condition);
            $conditionResults[] = $result;

            if (! $result['passed']) {
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'blocked_by_conditions' => true,
                    'condition_results' => $conditionResults,
                    'action_results' => [],
                ];
            }
        }

        $actionResults = [];
        foreach ($actions as $action) {
            if (($action['enabled'] ?? true) === false) {
                continue;
            }

            $result = $this->executeAction($studentEnrollment, $action, $step, $payload);
            $actionResults[] = $result;

            if (! $result['success'] && ($action['halt_on_failure'] ?? true)) {
                return [
                    'success' => false,
                    'message' => $result['message'],
                    'blocked_by_conditions' => false,
                    'condition_results' => $conditionResults,
                    'action_results' => $actionResults,
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Step executed successfully.',
            'blocked_by_conditions' => false,
            'condition_results' => $conditionResults,
            'action_results' => $actionResults,
        ];
    }

    public function execute(StudentEnrollment $studentEnrollment, array $step, array $payload = []): array
    {
        return $this->executeStep($studentEnrollment, $step, $payload);
    }

    private function normalizeConditions(array $step): array
    {
        $conditions = $step['node_conditions'] ?? [];

        if (! is_array($conditions)) {
            return [];
        }

        usort($conditions, fn (array $left, array $right): int => ((int) ($left['order'] ?? 1)) <=> ((int) ($right['order'] ?? 1)));

        return $conditions;
    }

    private function normalizeActions(array $step): array
    {
        $nodeActions = $step['node_actions'] ?? [];
        if (is_array($nodeActions) && $nodeActions !== []) {
            usort($nodeActions, fn (array $left, array $right): int => ((int) ($left['order'] ?? 1)) <=> ((int) ($right['order'] ?? 1)));

            return $nodeActions;
        }

        $legacyActions = $step['actions'] ?? null;
        if (! is_array($legacyActions)) {
            $actionTypeStep = $this->pipelineService->getStepByActionType((string) ($step['action_type'] ?? 'standard'));
            $legacyActions = is_array($actionTypeStep['actions'] ?? null) ? $actionTypeStep['actions'] : [];
        }

        if (! is_array($legacyActions)) {
            return [];
        }

        $actions = [];
        foreach ($legacyActions as $index => $legacyAction) {
            if (! is_string($legacyAction)) {
                continue;
            }

            $mapped = match (mb_strtolower(mb_trim($legacyAction))) {
                'advance_status' => 'change_status',
                'department_verification' => 'department_verification',
                'cashier_verification' => 'cashier_verification',
                default => null,
            };

            if ($mapped === null) {
                continue;
            }

            $actions[] = ['type' => $mapped, 'order' => $index + 1, 'config' => []];
        }

        return $actions;
    }

    private function evaluateCondition(StudentEnrollment $studentEnrollment, array $condition): array
    {
        $type = is_string($condition['type'] ?? null) ? mb_strtolower(mb_trim((string) $condition['type'])) : '';
        $config = is_array($condition['config'] ?? null) ? $condition['config'] : [];

        $message = is_string($condition['message'] ?? null) && mb_trim((string) $condition['message']) !== ''
            ? mb_trim((string) $condition['message'])
            : 'Condition failed.';

        if ($type !== 'complete_student_profile') {
            return ['type' => $type, 'passed' => false, 'message' => 'Unsupported condition type.'];
        }

        $requiredFields = $config['required_fields'] ?? ['first_name', 'last_name', 'email'];
        if (! is_array($requiredFields) || $requiredFields === []) {
            $requiredFields = ['first_name', 'last_name', 'email'];
        }

        $student = $studentEnrollment->student;
        if ($student === null || $student->id === null) {
            return ['type' => $type, 'passed' => false, 'message' => $message];
        }

        $missing = [];
        foreach ($requiredFields as $requiredField) {
            if (! is_string($requiredField)) {
                continue;
            }

            $field = mb_trim($requiredField);
            if ($field === '') {
                continue;
            }

            $value = data_get($student, $field);
            if (! is_string($value) && ! is_numeric($value) && ! is_bool($value)) {
                $missing[] = $field;

                continue;
            }

            if (is_string($value) && mb_trim($value) === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            return [
                'type' => $type,
                'passed' => false,
                'message' => $message,
                'missing_fields' => $missing,
            ];
        }

        return ['type' => $type, 'passed' => true, 'message' => 'Condition passed.'];
    }

    private function executeAction(StudentEnrollment $studentEnrollment, array $action, array $step, array $payload): array
    {
        $type = is_string($action['type'] ?? null) ? mb_strtolower(mb_trim((string) $action['type'])) : '';
        $config = is_array($action['config'] ?? null) ? $action['config'] : [];

        return match ($type) {
            'change_status' => $this->executeChangeStatus($studentEnrollment, $step, $config),
            'department_verification' => $this->boolResult($type, $this->enrollmentService->runDepartmentVerification(
                $studentEnrollment,
                $this->resolveStatus($step, $config)
            )),
            'cashier_verification' => $this->boolResult($type, $this->enrollmentService->runCashierVerification(
                $studentEnrollment,
                $payload,
                $this->resolveStatus($step, $config)
            )),
            'sync_student_enrolled_status' => $this->executeSyncEnrolledStatus($studentEnrollment, $type),
            'auto_enroll_classes' => $this->boolResult($type, $this->enrollmentService->autoEnrollClassesForStudent($studentEnrollment)),
            'calculate_tuition' => $this->executeCalculateTuition($studentEnrollment, $type, $payload),
            'update_tuition' => $this->boolResult($type, $this->enrollmentService->updateTuitionForEnrollment($studentEnrollment, $payload)),
            'create_payment_transactions' => $this->boolResult($type, $this->enrollmentService->createPaymentTransactionsForEnrollment($studentEnrollment, $payload)),
            'send_email' => ['type' => $type, 'success' => false, 'message' => 'send_email requires integration-specific configuration.'],
            'send_notification' => ['type' => $type, 'success' => false, 'message' => 'send_notification requires integration-specific configuration.'],
            default => ['type' => $type, 'success' => false, 'message' => 'Unsupported action type.'],
        };
    }

    private function executeChangeStatus(StudentEnrollment $studentEnrollment, array $step, array $config): array
    {
        $status = $this->resolveStatus($step, $config);

        if ($status === null || $status === '') {
            $nextStep = $this->pipelineService->getNextStep($studentEnrollment->status);
            $status = is_string($nextStep['status'] ?? null) ? (string) $nextStep['status'] : null;
        }

        if ($status === null || $status === '') {
            return ['type' => 'change_status', 'success' => false, 'message' => 'Unable to resolve next status.'];
        }

        $studentEnrollment->status = $status;
        $studentEnrollment->save();

        return ['type' => 'change_status', 'success' => true, 'message' => 'Enrollment status updated.', 'status' => $status];
    }

    private function resolveStatus(array $step, array $config): ?string
    {
        if (is_string($config['status'] ?? null) && mb_trim((string) $config['status']) !== '') {
            return mb_trim((string) $config['status']);
        }

        if (is_string($step['status'] ?? null) && mb_trim((string) $step['status']) !== '') {
            return mb_trim((string) $step['status']);
        }

        return null;
    }

    private function executeSyncEnrolledStatus(StudentEnrollment $studentEnrollment, string $type): array
    {
        $this->enrollmentService->syncStudentEnrolledStatusPublic($studentEnrollment);

        return ['type' => $type, 'success' => true, 'message' => 'Student enrolled status synced.'];
    }

    private function executeCalculateTuition(StudentEnrollment $studentEnrollment, string $type, array $payload): array
    {
        $tuitionInput = $payload['tuition_data'] ?? $payload;
        if (! is_array($tuitionInput) || $tuitionInput === []) {
            if ($studentEnrollment->studentTuition !== null) {
                return ['type' => $type, 'success' => true, 'message' => 'Tuition already available.'];
            }

            return ['type' => $type, 'success' => false, 'message' => 'Missing tuition_data payload.'];
        }

        $tuition = $this->enrollmentService->calculateTuitionForEnrollment($studentEnrollment, $tuitionInput);

        return [
            'type' => $type,
            'success' => $tuition !== null,
            'message' => $tuition !== null ? 'Tuition calculated.' : 'Unable to calculate tuition.',
        ];
    }

    private function boolResult(string $type, bool $success): array
    {
        return [
            'type' => $type,
            'success' => $success,
            'message' => $success ? 'Action executed.' : 'Action failed.',
        ];
    }
}
