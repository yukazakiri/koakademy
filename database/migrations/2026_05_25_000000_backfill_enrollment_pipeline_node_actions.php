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

        DB::table('general_settings')
            ->select(['id', 'more_configs'])
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                $moreConfigs = $this->decodeToArray($record->more_configs);
                $pipeline = $this->decodeToArray($moreConfigs['enrollment_pipeline'] ?? []);

                if ($pipeline === [] || ! is_array($pipeline['steps'] ?? null)) {
                    return;
                }

                $pipeline['schema_version'] = max(2, (int) ($pipeline['schema_version'] ?? 2));
                $pipeline['steps'] = collect($pipeline['steps'])
                    ->map(fn (mixed $step): mixed => $this->backfillStep($step))
                    ->all();

                $moreConfigs['enrollment_pipeline'] = $pipeline;

                DB::table('general_settings')
                    ->where('id', $record->id)
                    ->update([
                        'more_configs' => $moreConfigs,
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void {}

    private function backfillStep(mixed $step): mixed
    {
        if (! is_array($step)) {
            return $step;
        }

        if (! is_array($step['node_actions'] ?? null) || $step['node_actions'] === []) {
            $legacyActions = is_array($step['actions'] ?? null)
                ? $step['actions']
                : $this->actionsForActionType((string) ($step['action_type'] ?? 'standard'));

            $step['node_actions'] = $this->nodeActionsFromLegacy($legacyActions);
        }

        if (! is_array($step['node_conditions'] ?? null)) {
            $step['node_conditions'] = [];
        }

        return $step;
    }

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

    private function actionsForActionType(string $actionType): array
    {
        return match ($actionType) {
            'department_verification' => ['department_verification'],
            'cashier_verification' => ['cashier_verification'],
            default => ['advance_status'],
        };
    }

    private function nodeActionsFromLegacy(array $legacyActions): array
    {
        $actions = [];

        foreach ($legacyActions as $index => $legacyAction) {
            if (! is_string($legacyAction)) {
                continue;
            }

            $actions[] = [
                'key' => $legacyAction,
                'type' => match ($legacyAction) {
                    'department_verification' => 'department_verification',
                    'cashier_verification' => 'cashier_verification',
                    default => 'change_status',
                },
                'enabled' => true,
                'order' => $index + 1,
                'config' => [],
                'halt_on_failure' => true,
            ];
        }

        return $actions === []
            ? [['key' => 'advance_status', 'type' => 'change_status', 'enabled' => true, 'order' => 1, 'config' => [], 'halt_on_failure' => true]]
            : $actions;
    }
};
