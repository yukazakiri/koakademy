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

            $actionConditionResult = $this->evaluateActionConditions($studentEnrollment, $action);
            if (! $actionConditionResult['passed']) {
                $actionResults[] = [
                    'type' => is_string($action['type'] ?? null) ? $action['type'] : '',
                    'success' => true,
                    'skipped' => true,
                    'message' => 'Action skipped because conditions did not match.',
                    'condition_results' => $actionConditionResult['condition_results'],
                ];

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
            'create_subject_enrollments' => $this->boolResult($type, $this->enrollmentService->createSubjectEnrollmentsForEnrollment($studentEnrollment, $payload)),
            'create_additional_fees' => $this->boolResult($type, $this->enrollmentService->createAdditionalFeesForEnrollment($studentEnrollment, $payload)),
            'send_department_verification_notification' => $this->boolResult($type, $this->enrollmentService->sendDepartmentVerificationNotification($studentEnrollment)),
            'manual_cashier_verification' => $this->boolResult($type, $this->enrollmentService->applyManualCashierVerification($studentEnrollment, $payload)),
            'sync_student_enrolled_status' => $this->executeSyncEnrolledStatus($studentEnrollment, $type),
            'auto_enroll_classes' => $this->boolResult($type, $this->enrollmentService->autoEnrollClassesForStudent($studentEnrollment)),
            'calculate_tuition' => $this->executeCalculateTuition($studentEnrollment, $type, $payload),
            'update_tuition' => $this->boolResult($type, $this->enrollmentService->updateTuitionForEnrollment($studentEnrollment, $payload)),
            'create_payment_transactions' => $this->boolResult($type, $this->enrollmentService->createPaymentTransactionsForEnrollment($studentEnrollment, $payload)),
            'apply_cashier_payment' => $this->boolResult($type, $this->enrollmentService->applyCashierPaymentToTuition($studentEnrollment, $payload)),
            'update_student_academic_year' => $this->boolResult($type, $this->enrollmentService->updateStudentAcademicYearFromEnrollment($studentEnrollment)),
            'link_first_year_student_account' => $this->boolResult($type, $this->enrollmentService->linkFirstYearStudentAccount($studentEnrollment)),
            'send_student_migrated_notification' => $this->boolResult($type, $this->enrollmentService->sendStudentMigratedNotification($studentEnrollment)),
            'send_super_admin_enrollment_notification' => $this->boolResult($type, $this->enrollmentService->sendSuperAdminEnrollmentNotification($studentEnrollment, $payload)),
            'send_email' => ['type' => $type, 'success' => false, 'message' => 'send_email requires integration-specific configuration.'],
            'send_notification' => ['type' => $type, 'success' => false, 'message' => 'send_notification requires integration-specific configuration.'],
            default => ['type' => $type, 'success' => false, 'message' => 'Unsupported action type.'],
        };
    }

    private function evaluateActionConditions(StudentEnrollment $studentEnrollment, array $action): array
    {
        $conditions = $action['conditions'] ?? [];
        if (! is_array($conditions) || $conditions === []) {
            return ['passed' => true, 'condition_results' => []];
        }

        $activeConditions = array_values(array_filter($conditions, fn (mixed $condition): bool => is_array($condition) && ($condition['enabled'] ?? true) !== false));
        if ($activeConditions === []) {
            return ['passed' => true, 'condition_results' => []];
        }

        $results = array_map(fn (array $condition): array => $this->evaluateActionCondition($studentEnrollment, $condition), $activeConditions);
        $logic = is_string($action['condition_logic'] ?? null) && mb_strtolower(mb_trim((string) $action['condition_logic'])) === 'any' ? 'any' : 'all';

        $passed = $logic === 'any'
            ? collect($results)->contains(fn (array $result): bool => $result['passed'] === true)
            : collect($results)->every(fn (array $result): bool => $result['passed'] === true);

        return ['passed' => $passed, 'condition_results' => $results];
    }

    private function evaluateActionCondition(StudentEnrollment $studentEnrollment, array $condition): array
    {
        $type = is_string($condition['type'] ?? null) ? mb_strtolower(mb_trim((string) $condition['type'])) : '';
        $operator = is_string($condition['operator'] ?? null) ? mb_strtolower(mb_trim((string) $condition['operator'])) : 'eq';
        $expected = $condition['value'] ?? null;
        $actual = $this->resolveActionConditionActualValue($studentEnrollment, $type);

        return [
            'type' => $type,
            'operator' => $operator,
            'expected' => $expected,
            'actual' => $actual,
            'passed' => $this->compareActionCondition($actual, $operator, $expected),
        ];
    }

    private function resolveActionConditionActualValue(StudentEnrollment $studentEnrollment, string $type): bool|float|int|string|null
    {
        $studentEnrollment->loadMissing(['student', 'course', 'subjectsEnrolled.subject', 'studentTuition']);

        $student = $studentEnrollment->student;
        $tuition = $studentEnrollment->studentTuition;
        $subjects = $studentEnrollment->subjectsEnrolled;

        return match ($type) {
            'total_units' => $subjects->sum(fn ($subjectEnrollment): float => (float) ($subjectEnrollment->enrolled_lecture_units ?? 0) + (float) ($subjectEnrollment->enrolled_laboratory_units ?? 0) + (float) ($subjectEnrollment->external_subject_units ?? 0)),
            'subject_count' => $subjects->count(),
            'year_level' => (int) ($studentEnrollment->course?->year_level ?? $student?->academic_year ?? 0),
            'semester' => (int) ($studentEnrollment->semester ?? 0),
            'gpa' => (float) $subjects->filter(fn ($subjectEnrollment): bool => is_numeric($subjectEnrollment->grade))->avg('grade'),
            'failed_subjects' => $subjects->filter(fn ($subjectEnrollment): bool => is_numeric($subjectEnrollment->grade) && (float) $subjectEnrollment->grade > 3.0)->count(),
            'has_balance' => (float) ($tuition?->total_balance ?? 0) > 0,
            'balance_amount' => (float) ($tuition?->total_balance ?? 0),
            'has_paid_full' => $tuition !== null && (float) ($tuition->total_balance ?? 0) <= 0,
            'has_paid_partial' => (float) ($tuition?->total_paid ?? 0) > 0 && (float) ($tuition?->total_balance ?? 0) > 0,
            'amount_paid' => (float) ($tuition?->total_paid ?? 0),
            'has_scholarship' => $student?->hasScholarship() ?? false,
            'is_first_year' => (int) ($studentEnrollment->course?->year_level ?? $student?->academic_year ?? 0) === 1,
            'is_transferee' => mb_strtolower((string) data_get($student, 'student_type.value', $student?->student_type ?? '')) === 'transferee',
            'is_regular' => mb_strtolower((string) data_get($student, 'status.value', $student?->status ?? '')) === 'regular',
            'is_new_student' => mb_strtolower((string) data_get($student, 'status.value', $student?->status ?? '')) === 'new',
            'has_incomplete_grades' => $subjects->contains(fn ($subjectEnrollment): bool => is_string($subjectEnrollment->grade) && mb_strtolower(mb_trim($subjectEnrollment->grade)) === 'inc'),
            'has_prerequisites' => true,
            'age' => $student?->birth_date !== null ? $student->birth_date->age : null,
            'gender' => (string) ($student?->gender ?? ''),
            'course' => (string) ($studentEnrollment->course?->code ?? $student?->Course?->code ?? $studentEnrollment->course_id ?? ''),
            default => null,
        };
    }

    private function compareActionCondition(bool|float|int|string|null $actual, string $operator, mixed $expected): bool
    {
        if (is_bool($actual)) {
            $expectedValue = filter_var($expected, FILTER_VALIDATE_BOOLEAN);

            return $operator === 'neq' ? $actual !== $expectedValue : $actual === $expectedValue;
        }

        if (is_numeric($actual) && is_numeric($expected)) {
            $actualNumber = (float) $actual;
            $expectedNumber = (float) $expected;

            return match ($operator) {
                'neq' => $actualNumber !== $expectedNumber,
                'gt' => $actualNumber > $expectedNumber,
                'gte' => $actualNumber >= $expectedNumber,
                'lt' => $actualNumber < $expectedNumber,
                'lte' => $actualNumber <= $expectedNumber,
                default => $actualNumber === $expectedNumber,
            };
        }

        $actualString = mb_strtolower(mb_trim((string) $actual));
        $expectedString = mb_strtolower(mb_trim((string) $expected));

        return $operator === 'neq' ? $actualString !== $expectedString : $actualString === $expectedString;
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
