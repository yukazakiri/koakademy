<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EnrollStat;
use App\Models\GeneralSetting;
use App\Models\User;

final class EnrollmentPipelineService
{
    public function hasWorkflowSetup(): bool
    {
        $settings = GeneralSetting::query()->first();
        $raw = data_get($settings?->more_configs, 'enrollment_pipeline', []);

        if (! is_array($raw)) {
            return false;
        }

        if (is_array($raw['steps'] ?? null) && $raw['steps'] !== []) {
            return true;
        }

        $legacyStatuses = [
            $raw['pending_status'] ?? null,
            $raw['department_verified_status'] ?? null,
            $raw['cashier_verified_status'] ?? null,
        ];

        foreach ($legacyStatuses as $status) {
            if (is_string($status) && mb_trim($status) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        $settings = GeneralSetting::query()->first();
        $raw = data_get($settings?->more_configs, 'enrollment_pipeline', []);

        if (! is_array($raw)) {
            $raw = [];
        }

        return $this->sanitizeForStorage($raw);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStatsConfiguration(): array
    {
        $settings = GeneralSetting::query()->first();
        $raw = data_get($settings?->more_configs, 'enrollment_stats', []);

        if (! is_array($raw)) {
            $raw = [];
        }

        return $this->sanitizeStatsForStorage($raw);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitizeForStorage(array $input): array
    {
        $defaults = $this->defaults();

        $steps = $this->sanitizeSteps($input['steps'] ?? null);

        if ($steps === []) {
            $steps = $this->sanitizeLegacySteps($input);
        }

        if ($steps === []) {
            $steps = $defaults['steps'];
        }

        if (! $this->hasUniqueStepKeysAndStatuses($steps)) {
            $steps = $defaults['steps'];
        }

        $entryStepKey = $this->sanitizeString($input['entry_step_key'] ?? null, $steps[0]['key']);
        $completionStepKey = $this->sanitizeString($input['completion_step_key'] ?? null, $steps[count($steps) - 1]['key']);

        if (! $this->stepKeyExists($steps, $entryStepKey)) {
            $entryStepKey = $steps[0]['key'];
        }

        if (! $this->stepKeyExists($steps, $completionStepKey)) {
            $completionStepKey = $steps[count($steps) - 1]['key'];
        }

        $config = [
            'submitted_label' => $this->sanitizeString($input['submitted_label'] ?? null, $defaults['submitted_label']),
            'steps' => $steps,
            'entry_step_key' => $entryStepKey,
            'completion_step_key' => $completionStepKey,
        ];

        return $this->appendLegacyAliases($config);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function sanitizeStatsForStorage(array $input): array
    {
        $pipelineStatuses = collect($this->getSteps())
            ->pluck('status')
            ->values()
            ->all();

        $defaults = $this->defaultStatsConfiguration($pipelineStatuses);
        $cardsRaw = $input['cards'] ?? [];

        if (! is_array($cardsRaw)) {
            return $defaults;
        }

        $cards = [];
        foreach ($cardsRaw as $index => $cardRaw) {
            if (! is_array($cardRaw)) {
                continue;
            }

            $label = $this->sanitizeString($cardRaw['label'] ?? null, '');
            $metric = $this->sanitizeStatsMetric($cardRaw['metric'] ?? null);

            if ($label === '') {
                continue;
            }

            $key = $this->sanitizeStepKey($cardRaw['key'] ?? ('card_'.($index + 1)), 'card_'.($index + 1));
            $statuses = $this->sanitizeStatuses($cardRaw['statuses'] ?? [], $pipelineStatuses);

            if ($metric === 'status_count' && $statuses === []) {
                continue;
            }

            $cards[] = [
                'key' => $key,
                'label' => $label,
                'metric' => $metric,
                'statuses' => $statuses,
                'color' => $this->sanitizeColor($cardRaw['color'] ?? null, 'blue'),
            ];
        }

        if ($cards === []) {
            return $defaults;
        }

        return ['cards' => $cards];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSteps(): array
    {
        $config = $this->getConfiguration();
        $completionStepKey = $config['completion_step_key'] ?? null;

        return collect($config['steps'])
            ->map(function (array $step) use ($completionStepKey): array {
                return [
                    'status' => $step['status'],
                    'label' => $step['label'],
                    'color' => $step['color'],
                    'allowed_roles' => $step['allowed_roles'],
                    'is_core' => false,
                    'key' => $step['key'],
                    'action_type' => $step['action_type'],
                    'is_completion' => $completionStepKey === $step['key'],
                ];
            })
            ->values()
            ->all();
    }

    public function getPendingStatus(): string
    {
        return $this->getEntryStep()['status'];
    }

    public function getDepartmentVerifiedStatus(): string
    {
        $departmentStep = $this->getStepByActionType('department_verification');

        if ($departmentStep !== null) {
            return $departmentStep['status'];
        }

        $steps = $this->getSteps();

        return $steps[1]['status'] ?? $this->getPendingStatus();
    }

    public function getCashierVerifiedStatus(): string
    {
        $cashierStep = $this->getStepByActionType('cashier_verification');

        if ($cashierStep !== null) {
            return $cashierStep['status'];
        }

        return $this->getCompletionStep()['status'];
    }

    public function isPending(?string $status): bool
    {
        return $status === $this->getPendingStatus();
    }

    public function isDepartmentVerified(?string $status): bool
    {
        return $status === $this->getDepartmentVerifiedStatus();
    }

    public function isCashierVerified(?string $status): bool
    {
        return $status === $this->getCashierVerifiedStatus();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStepByStatus(?string $status): ?array
    {
        if ($status === null || $status === '') {
            return null;
        }

        foreach ($this->getSteps() as $step) {
            if ($step['status'] === $status) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStepByActionType(string $actionType): ?array
    {
        foreach ($this->getSteps() as $step) {
            if (($step['action_type'] ?? 'standard') === $actionType) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getEntryStep(): array
    {
        $config = $this->getConfiguration();

        foreach ($this->getSteps() as $step) {
            if ($step['key'] === $config['entry_step_key']) {
                return $step;
            }
        }

        return $this->getSteps()[0];
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompletionStep(): array
    {
        $config = $this->getConfiguration();
        $steps = $this->getSteps();

        foreach ($steps as $step) {
            if ($step['key'] === $config['completion_step_key']) {
                return $step;
            }
        }

        return $steps[count($steps) - 1];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNextStep(?string $currentStatus): ?array
    {
        $steps = $this->getSteps();
        foreach ($steps as $index => $step) {
            if ($step['status'] !== $currentStatus) {
                continue;
            }

            return $steps[$index + 1] ?? null;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getPreviousStep(?string $currentStatus): ?array
    {
        $steps = $this->getSteps();
        foreach ($steps as $index => $step) {
            if ($step['status'] !== $currentStatus) {
                continue;
            }

            if ($index === 0) {
                return null;
            }

            return $steps[$index - 1];
        }

        return null;
    }

    public function canUserAdvanceToNextStep(User $user, ?string $currentStatus): bool
    {
        $nextStep = $this->getNextStep($currentStatus);
        if ($nextStep === null) {
            return false;
        }

        return $this->canUserPerformStep($user, $nextStep);
    }

    /**
     * @param  array{allowed_roles: array<int, string>}  $step
     */
    public function canUserPerformStep(User $user, array $step): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        $roles = $step['allowed_roles'];
        if ($roles === []) {
            return true;
        }

        return $user->hasAnyRole($roles);
    }

    /**
     * @return array<string, string>
     */
    public function getStatusLabels(): array
    {
        $labels = [];
        foreach ($this->getSteps() as $step) {
            $labels[$step['status']] = $step['label'];
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    public function getStatusColorClasses(): array
    {
        $classes = [];
        foreach ($this->getSteps() as $step) {
            $classes[$step['status']] = $this->mapColorToTailwind($step['color']);
        }

        return $classes;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getStatusOptions(): array
    {
        return collect($this->getSteps())
            ->map(fn (array $step): array => ['value' => $step['status'], 'label' => $step['label']])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function getEnrolledStatuses(): array
    {
        $pendingStatus = $this->getPendingStatus();

        return collect($this->getSteps())
            ->pluck('status')
            ->filter(fn (string $status): bool => $status !== $pendingStatus)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'submitted_label' => 'Submitted',
            'steps' => [
                [
                    'key' => 'pending',
                    'status' => EnrollStat::Pending->value,
                    'label' => 'Department Verification',
                    'color' => 'yellow',
                    'allowed_roles' => [],
                    'action_type' => 'standard',
                ],
                [
                    'key' => 'department_verification',
                    'status' => EnrollStat::VerifiedByDeptHead->value,
                    'label' => 'Cashier Verification',
                    'color' => 'blue',
                    'allowed_roles' => [],
                    'action_type' => 'department_verification',
                ],
                [
                    'key' => 'payment_verification',
                    'status' => EnrollStat::VerifiedByCashier->value,
                    'label' => 'Enrolled',
                    'color' => 'green',
                    'allowed_roles' => [],
                    'action_type' => 'cashier_verification',
                ],
            ],
            'entry_step_key' => 'pending',
            'completion_step_key' => 'payment_verification',
        ];
    }

    private function sanitizeString(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? $fallback : $trimmed;
    }

    /**
     * @return array<int, string>
     */
    private function sanitizeRoles(mixed $roles): array
    {
        if (! is_array($roles)) {
            return [];
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (! is_string($role)) {
                continue;
            }

            $roleName = mb_trim($role);
            if ($roleName === '') {
                continue;
            }

            $normalized[] = $roleName;
        }

        return array_values(array_unique($normalized));
    }

    private function sanitizeColor(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $color = mb_trim(mb_strtolower($value));
        $allowed = [
            'yellow',
            'blue',
            'green',
            'emerald',
            'teal',
            'gray',
            'red',
            'amber',
            'indigo',
            'orange',
        ];

        return in_array($color, $allowed, true) ? $color : $fallback;
    }

    private function sanitizeStatsMetric(mixed $metric): string
    {
        if (! is_string($metric)) {
            return 'total_records';
        }

        $normalized = mb_trim(mb_strtolower($metric));
        $allowed = ['total_records', 'active_records', 'trashed_records', 'status_count', 'paid_count'];

        return in_array($normalized, $allowed, true) ? $normalized : 'total_records';
    }

    /**
     * @param  array<int, string>  $allowedStatuses
     * @return array<int, string>
     */
    private function sanitizeStatuses(mixed $statuses, array $allowedStatuses): array
    {
        if (! is_array($statuses)) {
            return [];
        }

        $normalized = [];
        foreach ($statuses as $status) {
            if (! is_string($status)) {
                continue;
            }

            $statusValue = mb_trim($status);
            if ($statusValue === '') {
                continue;
            }

            if (! in_array($statusValue, $allowedStatuses, true)) {
                continue;
            }

            $normalized[] = $statusValue;
        }

        return array_values(array_unique($normalized));
    }

    private function sanitizeActionType(mixed $actionType): string
    {
        if (! is_string($actionType)) {
            return 'standard';
        }

        $normalized = mb_trim(mb_strtolower($actionType));

        return in_array($normalized, ['standard', 'department_verification', 'cashier_verification'], true)
            ? $normalized
            : 'standard';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeSteps(mixed $stepsRaw): array
    {
        if (! is_array($stepsRaw)) {
            return [];
        }

        $steps = [];
        foreach ($stepsRaw as $index => $stepRaw) {
            if (! is_array($stepRaw)) {
                continue;
            }

            $status = $this->sanitizeString($stepRaw['status'] ?? null, '');
            $label = $this->sanitizeString($stepRaw['label'] ?? null, '');

            if ($status === '' || $label === '') {
                continue;
            }

            $generatedKey = 'step_'.($index + 1);
            $key = $this->sanitizeStepKey($stepRaw['key'] ?? $generatedKey, $generatedKey);

            $steps[] = [
                'key' => $key,
                'status' => $status,
                'label' => $label,
                'color' => $this->sanitizeColor($stepRaw['color'] ?? null, 'gray'),
                'allowed_roles' => $this->sanitizeRoles($stepRaw['allowed_roles'] ?? []),
                'action_type' => $this->sanitizeActionType($stepRaw['action_type'] ?? 'standard'),
            ];
        }

        return array_values($steps);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeLegacySteps(array $input): array
    {
        $defaults = $this->defaults();

        $pendingStatus = $this->sanitizeString($input['pending_status'] ?? null, $defaults['steps'][0]['status']);
        $pendingLabel = $this->sanitizeString($input['pending_label'] ?? null, $defaults['steps'][0]['label']);

        $departmentStatus = $this->sanitizeString($input['department_verified_status'] ?? null, $defaults['steps'][1]['status']);
        $departmentLabel = $this->sanitizeString($input['department_verified_label'] ?? null, $defaults['steps'][1]['label']);

        $cashierStatus = $this->sanitizeString($input['cashier_verified_status'] ?? null, $defaults['steps'][2]['status']);
        $cashierLabel = $this->sanitizeString($input['cashier_verified_label'] ?? null, $defaults['steps'][2]['label']);

        $steps = [
            [
                'key' => 'pending',
                'status' => $pendingStatus,
                'label' => $pendingLabel,
                'color' => $this->sanitizeColor($input['pending_color'] ?? null, $defaults['steps'][0]['color']),
                'allowed_roles' => $this->sanitizeRoles($input['pending_roles'] ?? []),
                'action_type' => 'standard',
            ],
            [
                'key' => 'department_verification',
                'status' => $departmentStatus,
                'label' => $departmentLabel,
                'color' => $this->sanitizeColor($input['department_verified_color'] ?? null, $defaults['steps'][1]['color']),
                'allowed_roles' => $this->sanitizeRoles($input['department_verified_roles'] ?? []),
                'action_type' => 'department_verification',
            ],
        ];

        $additionalStepsRaw = $input['additional_steps'] ?? [];
        if (is_array($additionalStepsRaw)) {
            foreach ($additionalStepsRaw as $index => $stepRaw) {
                if (! is_array($stepRaw)) {
                    continue;
                }

                $status = $this->sanitizeString($stepRaw['status'] ?? null, '');
                $label = $this->sanitizeString($stepRaw['label'] ?? null, '');

                if ($status === '' || $label === '') {
                    continue;
                }

                $steps[] = [
                    'key' => 'additional_'.($index + 1),
                    'status' => $status,
                    'label' => $label,
                    'color' => $this->sanitizeColor($stepRaw['color'] ?? null, 'indigo'),
                    'allowed_roles' => $this->sanitizeRoles($stepRaw['allowed_roles'] ?? []),
                    'action_type' => 'standard',
                ];
            }
        }

        $steps[] = [
            'key' => 'payment_verification',
            'status' => $cashierStatus,
            'label' => $cashierLabel,
            'color' => $this->sanitizeColor($input['cashier_verified_color'] ?? null, $defaults['steps'][2]['color']),
            'allowed_roles' => $this->sanitizeRoles($input['cashier_verified_roles'] ?? []),
            'action_type' => 'cashier_verification',
        ];

        return $steps;
    }

    private function sanitizeStepKey(mixed $value, string $fallback): string
    {
        if (! is_string($value)) {
            return $fallback;
        }

        $slug = str($value)->slug('_')->toString();

        return $slug === '' ? $fallback : $slug;
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function hasUniqueStepKeysAndStatuses(array $steps): bool
    {
        $keys = collect($steps)->pluck('key')->all();
        $statuses = collect($steps)->pluck('status')->all();

        return count($keys) === count(array_unique($keys))
            && count($statuses) === count(array_unique($statuses));
    }

    /**
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function stepKeyExists(array $steps, string $key): bool
    {
        foreach ($steps as $step) {
            if (($step['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function appendLegacyAliases(array $config): array
    {
        $steps = is_array($config['steps']) ? $config['steps'] : [];

        if ($steps === []) {
            $defaults = $this->defaults();
            $steps = $defaults['steps'];
            $config['steps'] = $steps;
            $config['entry_step_key'] = $defaults['entry_step_key'];
            $config['completion_step_key'] = $defaults['completion_step_key'];
        }

        $entryStep = $steps[0];
        foreach ($steps as $step) {
            if (($step['key'] ?? null) === $config['entry_step_key']) {
                $entryStep = $step;
            }
        }

        $completionStep = $steps[count($steps) - 1];
        foreach ($steps as $step) {
            if (($step['key'] ?? null) === $config['completion_step_key']) {
                $completionStep = $step;
            }
        }

        $departmentStep = null;
        $cashierStep = null;

        foreach ($steps as $step) {
            if (($step['action_type'] ?? null) === 'department_verification' && $departmentStep === null) {
                $departmentStep = $step;
            }

            if (($step['action_type'] ?? null) === 'cashier_verification' && $cashierStep === null) {
                $cashierStep = $step;
            }
        }

        $departmentStep ??= $steps[1] ?? $entryStep;
        $cashierStep ??= $completionStep;

        $additionalSteps = [];
        foreach ($steps as $step) {
            if (in_array($step['key'], [$entryStep['key'], $departmentStep['key'], $cashierStep['key']], true)) {
                continue;
            }

            $additionalSteps[] = [
                'status' => $step['status'],
                'label' => $step['label'],
                'color' => $step['color'],
                'allowed_roles' => $step['allowed_roles'],
            ];
        }

        return [
            ...$config,
            'pending_status' => $entryStep['status'],
            'pending_label' => $entryStep['label'],
            'pending_color' => $entryStep['color'],
            'pending_roles' => $entryStep['allowed_roles'],
            'department_verified_status' => $departmentStep['status'],
            'department_verified_label' => $departmentStep['label'],
            'department_verified_color' => $departmentStep['color'],
            'department_verified_roles' => $departmentStep['allowed_roles'],
            'cashier_verified_status' => $cashierStep['status'],
            'cashier_verified_label' => $cashierStep['label'],
            'cashier_verified_color' => $cashierStep['color'],
            'cashier_verified_roles' => $cashierStep['allowed_roles'],
            'additional_steps' => $additionalSteps,
        ];
    }

    /**
     * @param  array<int, string>  $pipelineStatuses
     * @return array<string, mixed>
     */
    private function defaultStatsConfiguration(array $pipelineStatuses): array
    {
        $pendingStatus = $this->getPendingStatus();
        $completionStatus = $this->getCompletionStep()['status'];

        return [
            'cards' => [
                [
                    'key' => 'total_records',
                    'label' => 'Total Records',
                    'metric' => 'total_records',
                    'statuses' => [],
                    'color' => 'blue',
                ],
                [
                    'key' => 'pending_records',
                    'label' => 'Pending',
                    'metric' => 'status_count',
                    'statuses' => in_array($pendingStatus, $pipelineStatuses, true) ? [$pendingStatus] : [],
                    'color' => 'amber',
                ],
                [
                    'key' => 'completed_records',
                    'label' => 'Completed',
                    'metric' => 'status_count',
                    'statuses' => in_array($completionStatus, $pipelineStatuses, true) ? [$completionStatus] : [],
                    'color' => 'green',
                ],
            ],
        ];
    }

    private function mapColorToTailwind(string $color): string
    {
        return match ($color) {
            'yellow' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 border-yellow-200',
            'blue' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 border-blue-200',
            'green' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-200',
            'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200',
            'teal' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400 border-teal-200',
            'red' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-200',
            'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-200',
            'indigo' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 border-indigo-200',
            'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400 border-orange-200',
            default => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-400 border-gray-200',
        };
    }
}
