<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('general_settings')) {
            return;
        }

        $records = DB::table('general_settings')
            ->select(['id', 'more_configs'])
            ->get();

        foreach ($records as $record) {
            $moreConfigs = $this->decodeToArray($record->more_configs);

            $pipeline = $this->decodeToArray($moreConfigs['enrollment_pipeline'] ?? []);
            $pipeline = $this->migratePipelineConfig($pipeline);
            $moreConfigs['enrollment_pipeline'] = $pipeline;

            $stats = $this->decodeToArray($moreConfigs['enrollment_stats'] ?? []);
            if (! isset($stats['cards']) || ! is_array($stats['cards']) || $stats['cards'] === []) {
                $stats['cards'] = $this->defaultStatsCards($pipeline);
            }
            $moreConfigs['enrollment_stats'] = $stats;

            DB::table('general_settings')
                ->where('id', $record->id)
                ->update([
                    'more_configs' => $moreConfigs,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No destructive rollback to avoid removing user-defined pipeline configuration.
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || mb_trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array<string, mixed>
     */
    private function migratePipelineConfig(array $pipeline): array
    {
        $hasSteps = isset($pipeline['steps']) && is_array($pipeline['steps']) && $pipeline['steps'] !== [];
        if ($hasSteps) {
            if (! isset($pipeline['entry_step_key']) || ! is_string($pipeline['entry_step_key']) || mb_trim($pipeline['entry_step_key']) === '') {
                $pipeline['entry_step_key'] = (string) ($pipeline['steps'][0]['key'] ?? 'pending');
            }

            if (! isset($pipeline['completion_step_key']) || ! is_string($pipeline['completion_step_key']) || mb_trim($pipeline['completion_step_key']) === '') {
                $pipeline['completion_step_key'] = (string) (collect($pipeline['steps'])->last()['key'] ?? 'payment_verification');
            }

            return $pipeline;
        }

        $pendingStatus = $this->stringOrDefault($pipeline['pending_status'] ?? null, 'Pending');
        $pendingLabel = $this->stringOrDefault($pipeline['pending_label'] ?? null, 'Department Verification');
        $pendingColor = $this->stringOrDefault($pipeline['pending_color'] ?? null, 'yellow');
        $pendingRoles = $this->normalizeRoles($pipeline['pending_roles'] ?? []);

        $departmentStatus = $this->stringOrDefault($pipeline['department_verified_status'] ?? null, 'Verified By Dept Head');
        $departmentLabel = $this->stringOrDefault($pipeline['department_verified_label'] ?? null, 'Cashier Verification');
        $departmentColor = $this->stringOrDefault($pipeline['department_verified_color'] ?? null, 'blue');
        $departmentRoles = $this->normalizeRoles($pipeline['department_verified_roles'] ?? []);

        $cashierStatus = $this->stringOrDefault($pipeline['cashier_verified_status'] ?? null, 'Verified By Cashier');
        $cashierLabel = $this->stringOrDefault($pipeline['cashier_verified_label'] ?? null, 'Enrolled');
        $cashierColor = $this->stringOrDefault($pipeline['cashier_verified_color'] ?? null, 'green');
        $cashierRoles = $this->normalizeRoles($pipeline['cashier_verified_roles'] ?? []);

        $steps = [
            [
                'key' => 'pending',
                'status' => $pendingStatus,
                'label' => $pendingLabel,
                'color' => $pendingColor,
                'allowed_roles' => $pendingRoles,
                'action_type' => 'standard',
            ],
            [
                'key' => 'department_verification',
                'status' => $departmentStatus,
                'label' => $departmentLabel,
                'color' => $departmentColor,
                'allowed_roles' => $departmentRoles,
                'action_type' => 'department_verification',
            ],
        ];

        $additionalSteps = $pipeline['additional_steps'] ?? [];
        if (is_array($additionalSteps)) {
            foreach ($additionalSteps as $index => $step) {
                if (! is_array($step)) {
                    continue;
                }

                $status = $this->stringOrDefault($step['status'] ?? null, '');
                $label = $this->stringOrDefault($step['label'] ?? null, '');
                if ($status === '') {
                    continue;
                }
                if ($label === '') {
                    continue;
                }

                $steps[] = [
                    'key' => 'additional_'.($index + 1),
                    'status' => $status,
                    'label' => $label,
                    'color' => $this->stringOrDefault($step['color'] ?? null, 'indigo'),
                    'allowed_roles' => $this->normalizeRoles($step['allowed_roles'] ?? []),
                    'action_type' => 'standard',
                ];
            }
        }

        $steps[] = [
            'key' => 'payment_verification',
            'status' => $cashierStatus,
            'label' => $cashierLabel,
            'color' => $cashierColor,
            'allowed_roles' => $cashierRoles,
            'action_type' => 'cashier_verification',
        ];

        return [
            ...$pipeline,
            'steps' => $steps,
            'entry_step_key' => 'pending',
            'completion_step_key' => 'payment_verification',
        ];
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? $default : $trimmed;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (! is_array($roles)) {
            return [];
        }

        return collect($roles)
            ->filter(fn ($role): bool => is_string($role) && mb_trim($role) !== '')
            ->map(fn (string $role): string => mb_trim($role))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $pipeline
     * @return array<int, array<string, mixed>>
     */
    private function defaultStatsCards(array $pipeline): array
    {
        $steps = collect($pipeline['steps'] ?? []);
        $entryKey = (string) ($pipeline['entry_step_key'] ?? 'pending');
        $completionKey = (string) ($pipeline['completion_step_key'] ?? 'payment_verification');

        $entryStatus = (string) ($steps->firstWhere('key', $entryKey)['status'] ?? 'Pending');
        $completionStatus = (string) ($steps->firstWhere('key', $completionKey)['status'] ?? 'Verified By Cashier');

        return [
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
                'statuses' => [$entryStatus],
                'color' => 'amber',
            ],
            [
                'key' => 'completed_records',
                'label' => 'Completed',
                'metric' => 'status_count',
                'statuses' => [$completionStatus],
                'color' => 'green',
            ],
        ];
    }
};
