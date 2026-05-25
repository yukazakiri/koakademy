<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateEnrollmentPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateEnrollmentPipeline', GeneralSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $stepActions = ['advance_status', 'department_verification', 'cashier_verification'];
        $stepTypes = ['standard', 'department_verification', 'cashier_verification'];
        $nodeActionTypes = [
            'change_status',
            'send_email',
            'send_notification',
            'department_verification',
            'cashier_verification',
            'sync_student_enrolled_status',
            'auto_enroll_classes',
            'calculate_tuition',
            'update_tuition',
            'create_payment_transactions',
        ];
        $nodeConditionTypes = ['complete_student_profile'];
        $statMetrics = ['total_records', 'active_records', 'trashed_records', 'status_count', 'paid_count'];

        return [
            'submitted_label' => ['required', 'string', 'max:100'],
            'schema_version' => ['nullable', 'integer', 'min:2'],
            'enrollment_courses' => ['nullable', 'array'],
            'enrollment_courses.*' => ['integer'],
            'steps' => ['nullable', 'array', 'min:1'],
            'steps.*.key' => ['nullable', 'string', 'max:100'],
            'steps.*.status' => ['required_with:steps', 'string', 'max:100'],
            'steps.*.label' => ['required_with:steps', 'string', 'max:100'],
            'steps.*.color' => ['required_with:steps', 'string', 'max:50'],
            'steps.*.allowed_roles' => ['nullable', 'array'],
            'steps.*.allowed_roles.*' => ['string', 'exists:roles,name'],
            'steps.*.action_type' => ['nullable', 'string', Rule::in($stepTypes)],
            'steps.*.actions' => ['nullable', 'array'],
            'steps.*.actions.*' => ['string', Rule::in($stepActions)],
            'steps.*.node_actions' => ['nullable', 'array'],
            'steps.*.node_actions.*.type' => ['required_with:steps.*.node_actions', 'string', Rule::in($nodeActionTypes)],
            'steps.*.node_actions.*.order' => ['nullable', 'integer', 'min:1'],
            'steps.*.node_actions.*.config' => ['nullable', 'array'],
            'steps.*.node_conditions' => ['nullable', 'array'],
            'steps.*.node_conditions.*.type' => ['required_with:steps.*.node_conditions', 'string', Rule::in($nodeConditionTypes)],
            'steps.*.node_conditions.*.order' => ['nullable', 'integer', 'min:1'],
            'steps.*.node_conditions.*.config' => ['nullable', 'array'],
            'steps.*.next_step_key' => ['nullable', 'string', 'max:100'],
            'entry_step_key' => ['nullable', 'string', 'max:100'],
            'completion_step_key' => ['nullable', 'string', 'max:100'],
            'automation' => ['nullable', 'array'],
            'automation.auto_create_student_enrollment' => ['nullable', 'boolean'],
            'automation.auto_assign_subjects' => ['nullable', 'boolean'],
            'automation.default_new_applicant_to_first_year' => ['nullable', 'boolean'],
            'pending_status' => ['required_without:steps', 'string', 'max:100'],
            'pending_label' => ['required_without:steps', 'string', 'max:100'],
            'pending_color' => ['required_without:steps', 'string', 'max:50'],
            'pending_roles' => ['nullable', 'array'],
            'pending_roles.*' => ['string', 'exists:roles,name'],
            'department_verified_status' => ['required_without:steps', 'string', 'max:100'],
            'department_verified_label' => ['required_without:steps', 'string', 'max:100'],
            'department_verified_color' => ['required_without:steps', 'string', 'max:50'],
            'department_verified_roles' => ['nullable', 'array'],
            'department_verified_roles.*' => ['string', 'exists:roles,name'],
            'cashier_verified_status' => ['required_without:steps', 'string', 'max:100'],
            'cashier_verified_label' => ['required_without:steps', 'string', 'max:100'],
            'cashier_verified_color' => ['required_without:steps', 'string', 'max:50'],
            'cashier_verified_roles' => ['nullable', 'array'],
            'cashier_verified_roles.*' => ['string', 'exists:roles,name'],
            'additional_steps' => ['nullable', 'array'],
            'additional_steps.*.status' => ['required', 'string', 'max:100'],
            'additional_steps.*.label' => ['required', 'string', 'max:100'],
            'additional_steps.*.color' => ['required', 'string', 'max:50'],
            'additional_steps.*.allowed_roles' => ['nullable', 'array'],
            'additional_steps.*.allowed_roles.*' => ['string', 'exists:roles,name'],
            'enrollment_stats' => ['nullable', 'array'],
            'enrollment_stats.cards' => ['nullable', 'array'],
            'enrollment_stats.cards.*.key' => ['nullable', 'string', 'max:100'],
            'enrollment_stats.cards.*.label' => ['required_with:enrollment_stats.cards', 'string', 'max:100'],
            'enrollment_stats.cards.*.metric' => ['required_with:enrollment_stats.cards', 'string', Rule::in($statMetrics)],
            'enrollment_stats.cards.*.statuses' => ['nullable', 'array'],
            'enrollment_stats.cards.*.statuses.*' => ['string', 'max:100'],
            'enrollment_stats.cards.*.color' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $steps = $this->input('steps', []);

                if (! is_array($steps) || $steps === []) {
                    return;
                }

                $this->validateUniqueStepValues($validator, $steps, 'key', 'step IDs');
                $this->validateUniqueStepValues($validator, $steps, 'status', 'status codes');
                $this->validateStepMarkerExists($validator, $steps, 'entry_step_key', 'starting step');
                $this->validateStepMarkerExists($validator, $steps, 'completion_step_key', 'completion step');
                $this->validateStepConnections($validator, $steps);
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steps.min' => 'Add at least one enrollment workflow step.',
            'steps.*.status.required_with' => 'Every workflow step needs a status code.',
            'steps.*.label.required_with' => 'Every workflow step needs a visible label.',
            'steps.*.allowed_roles.*.exists' => 'One of the selected step roles no longer exists.',
            'enrollment_stats.cards.*.label.required_with' => 'Every analytics card needs a label.',
            'enrollment_stats.cards.*.metric.required_with' => 'Every analytics card needs a metric type.',
        ];
    }

    /**
     * @param  array<int, mixed>  $steps
     */
    private function validateUniqueStepValues(Validator $validator, array $steps, string $field, string $label): void
    {
        $seenValues = [];

        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                continue;
            }

            $value = $step[$field] ?? null;
            if (! is_string($value) || mb_trim($value) === '') {
                continue;
            }

            $normalized = $field === 'key'
                ? str($value)->slug('_')->toString()
                : mb_strtolower(mb_trim($value));

            if (in_array($normalized, $seenValues, true)) {
                $validator->errors()->add("steps.{$index}.{$field}", "Workflow {$label} must be unique.");

                continue;
            }

            $seenValues[] = $normalized;
        }
    }

    /**
     * @param  array<int, mixed>  $steps
     */
    private function validateStepMarkerExists(Validator $validator, array $steps, string $field, string $label): void
    {
        $selectedKey = $this->input($field);
        if (! is_string($selectedKey) || mb_trim($selectedKey) === '') {
            return;
        }

        foreach ($steps as $step) {
            if (is_array($step) && ($step['key'] ?? null) === $selectedKey) {
                return;
            }
        }

        $validator->errors()->add($field, "The selected {$label} does not exist in the workflow steps.");
    }

    /**
     * @param  array<int, mixed>  $steps
     */
    private function validateStepConnections(Validator $validator, array $steps): void
    {
        $stepKeys = collect($steps)
            ->map(function (mixed $step, int $index): ?string {
                if (! is_array($step)) {
                    return null;
                }

                $key = $step['key'] ?? 'step_'.($index + 1);
                if (! is_string($key) || mb_trim($key) === '') {
                    return null;
                }

                return str($key)->slug('_')->toString();
            })
            ->filter()
            ->all();

        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                continue;
            }

            $nextStepKey = $step['next_step_key'] ?? null;
            if (! is_string($nextStepKey) || mb_trim($nextStepKey) === '') {
                continue;
            }

            $normalizedNextStepKey = str($nextStepKey)->slug('_')->toString();
            if (! in_array($normalizedNextStepKey, $stepKeys, true)) {
                $validator->errors()->add("steps.{$index}.next_step_key", 'The connected next step does not exist in this workflow.');
            }
        }
    }
}
