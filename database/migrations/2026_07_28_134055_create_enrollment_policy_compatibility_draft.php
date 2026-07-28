<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const string ChangeNotes = 'Compatibility draft: explicit database-driven enrollment runtime actions.';

    public function up(): void
    {
        $policies = DB::table('enrollment_policies')
            ->where('name', 'Global enrollment policy (migrated)')
            ->whereNotNull('active_version_id')
            ->get(['id', 'active_version_id']);

        foreach ($policies as $policy) {
            if (DB::table('enrollment_policy_versions')
                ->where('enrollment_policy_id', $policy->id)
                ->where('state', 'draft')
                ->where('change_notes', self::ChangeNotes)
                ->exists()) {
                continue;
            }

            $active = DB::table('enrollment_policy_versions')->where('id', $policy->active_version_id)->first();
            if ($active === null) {
                continue;
            }
            $configuration = json_decode((string) $active->configuration, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($configuration)) {
                continue;
            }

            $configuration = $this->explicitCompatibilityConfiguration($configuration);
            $encoded = json_encode($configuration, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $nextVersion = (int) DB::table('enrollment_policy_versions')
                ->where('enrollment_policy_id', $policy->id)
                ->max('version') + 1;

            DB::table('enrollment_policy_versions')->insert([
                'enrollment_policy_id' => $policy->id,
                'version' => $nextVersion,
                'state' => 'draft',
                'schema_version' => 1,
                'configuration' => $encoded,
                'checksum' => null,
                'change_notes' => self::ChangeNotes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('enrollment_policy_versions')
            ->where('state', 'draft')
            ->where('change_notes', self::ChangeNotes)
            ->delete();
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function explicitCompatibilityConfiguration(array $configuration): array
    {
        $configuration['schema_version'] = 1;
        $configuration['assignment'] = ['strategy' => 'assignment.manual', 'configuration' => []];
        $configuration['billing'] = [
            'strategy' => 'billing.course_rate',
            'configuration' => [
                'nstp_lecture_multiplier' => 0.5,
                'modular_laboratory_multiplier' => 0.5,
                'modular_fee' => 2400,
                'miscellaneous_fee_fallback' => 3500,
                'course_lecture_rate_per_unit' => null,
                'course_laboratory_rate_per_unit' => null,
                'course_miscellaneous_fee' => null,
                'discount_scope' => 'lecture_only',
                'allow_overall_override' => true,
                'receipt_mode' => 'required',
                'minimum_payment' => ['type' => 'none', 'value' => 0],
            ],
            'allowed_payment_methods' => [],
        ];
        foreach ($configuration['rules'] ?? [] as $index => $rule) {
            if (($rule['handler'] ?? null) !== 'availability.channel') {
                continue;
            }

            $configuration['rules'][$index]['configuration']['allowed'] = array_values(array_unique([
                ...($rule['configuration']['allowed'] ?? []),
                'api',
            ]));
        }

        $steps = $configuration['workflow']['steps'] ?? [];
        foreach ($steps as $index => $step) {
            if (($step['entry'] ?? false) === true) {
                $steps[$index]['actions'] = [
                    ['key' => 'assign_subjects', 'handler' => 'enrollment.assign_subjects', 'configuration' => ['source' => 'runtime_payload', 'allow_cross_program_subjects' => false]],
                    ['key' => 'assign_additional_fees', 'handler' => 'enrollment.assign_additional_fees', 'configuration' => []],
                    ['key' => 'reserve_selected_classes', 'handler' => 'enrollment.assign_classes', 'configuration' => ['mode' => 'runtime_payload', 'optional' => true]],
                    ['key' => 'calculate_tuition', 'handler' => 'enrollment.calculate_tuition', 'configuration' => []],
                ];
            } elseif (($step['terminal'] ?? false) === true) {
                $steps[$index]['actions'] = [
                    ['key' => 'payment_status', 'handler' => 'enrollment.verify_payment', 'configuration' => ['receipt_mode' => 'required', 'record_transaction' => true, 'allow_no_receipt' => false]],
                    ['key' => 'assign_remaining_classes', 'handler' => 'enrollment.assign_classes', 'configuration' => ['mode' => 'first_available', 'optional' => true]],
                    ['key' => 'sync_student', 'handler' => 'enrollment.sync_student', 'configuration' => ['attribute' => 'status', 'value' => 'enrolled', 'sync_account' => true]],
                    ['key' => 'complete_outcome', 'handler' => 'enrollment.set_outcome', 'configuration' => ['outcome' => 'completed']],
                    ['key' => 'generate_assessment', 'handler' => 'enrollment.generate_assessment', 'configuration' => ['create_new_file' => false]],
                    ['key' => 'notify_student', 'handler' => 'enrollment.notify', 'configuration' => ['notification' => 'assessment']],
                ];
            } else {
                $steps[$index]['actions'] = [
                    ['key' => 'academic_status', 'handler' => 'enrollment.verify_academic', 'configuration' => []],
                ];
            }
        }
        $configuration['workflow']['steps'] = $steps;
        $configuration['notifications'] = [];
        $configuration['compatibility'] = [
            ...($configuration['compatibility'] ?? []),
            'draft_only' => true,
            'explicit_runtime_actions' => true,
        ];

        return $configuration;
    }
};
