<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Enums\PaymentMethod;
use Illuminate\Validation\ValidationException;

final readonly class EnrollmentConfigurationValidator
{
    private const array SupportedControls = [
        'boolean', 'text', 'number', 'money', 'percentage', 'date', 'date_range',
        'select', 'multi_select', 'role', 'permission', 'school', 'program',
        'period', 'notification_channel',
    ];

    private const array SupportedOptionSources = [
        'enrollment_channels', 'student_types', 'schools', 'programs', 'periods',
        'year_levels', 'payment_methods', 'roles', 'permissions', 'notification_channels',
    ];

    public function __construct(private EnrollmentPolicyRegistry $registry) {}

    /** @param array<string, mixed> $configuration */
    public function validate(array $configuration): void
    {
        $manifest = $this->registry->manifest();

        foreach ($configuration['rules'] ?? [] as $index => $rule) {
            $handler = $rule['handler'] ?? null;
            $this->validateItem(
                $rule['configuration'] ?? [],
                is_string($handler) ? ($manifest['rules'][$handler]['operator_schema'] ?? null) : null,
                "rules.{$index}.configuration",
            );
        }

        foreach (data_get($configuration, 'workflow.steps', []) as $stepIndex => $step) {
            foreach ($step['actions'] ?? [] as $actionIndex => $action) {
                $handler = $action['handler'] ?? null;
                $this->validateItem(
                    $action['configuration'] ?? [],
                    is_string($handler) ? ($manifest['actions'][$handler]['operator_schema'] ?? null) : null,
                    "workflow.steps.{$stepIndex}.actions.{$actionIndex}.configuration",
                );
            }

            foreach ($step['transitions'] ?? [] as $transitionIndex => $transition) {
                foreach ($transition['conditions'] ?? [] as $conditionIndex => $condition) {
                    $handler = $condition['handler'] ?? null;
                    $this->validateItem(
                        $condition['configuration'] ?? [],
                        is_string($handler) ? ($manifest['rules'][$handler]['operator_schema'] ?? null) : null,
                        "workflow.steps.{$stepIndex}.transitions.{$transitionIndex}.conditions.{$conditionIndex}.configuration",
                    );
                }
            }
        }

        $assignmentStrategy = data_get($configuration, 'assignment.strategy');
        $this->validateItem(
            data_get($configuration, 'assignment.configuration', []),
            is_string($assignmentStrategy) ? ($manifest['assignment_strategies'][$assignmentStrategy]['operator_schema'] ?? null) : null,
            'assignment.configuration',
        );
        $billingStrategy = data_get($configuration, 'billing.strategy');
        $this->validateItem(
            data_get($configuration, 'billing.configuration', []),
            is_string($billingStrategy) ? ($manifest['billing_strategies'][$billingStrategy]['operator_schema'] ?? null) : null,
            'billing.configuration',
        );

        $stepKeys = collect(data_get($configuration, 'workflow.steps', []))->pluck('key')->filter()->all();
        foreach ($configuration['requirements'] ?? [] as $index => $requirement) {
            if (! is_array($requirement) || ! is_string($requirement['key'] ?? null) || ! is_string($requirement['label'] ?? null)) {
                throw ValidationException::withMessages(["requirements.{$index}" => 'Every requirement needs a stable key and label.']);
            }
            $enforcementStep = $requirement['enforcement_step'] ?? null;
            if ($enforcementStep !== null && (! is_string($enforcementStep) || ! in_array($enforcementStep, $stepKeys, true))) {
                throw ValidationException::withMessages(["requirements.{$index}.enforcement_step" => 'The requirement enforcement step must exist in this workflow.']);
            }
        }
        foreach (data_get($configuration, 'workflow.steps', []) as $stepIndex => $step) {
            foreach ($step['transitions'] ?? [] as $transitionIndex => $transition) {
                if (isset($transition['requires_reason']) && ! is_bool($transition['requires_reason'])) {
                    throw ValidationException::withMessages([
                        "workflow.steps.{$stepIndex}.transitions.{$transitionIndex}.requires_reason" => 'The audited-reason setting must be true or false.',
                    ]);
                }
            }
        }
        $noReceiptTargets = collect(data_get($configuration, 'workflow.steps', []))
            ->filter(fn (array $step): bool => collect($step['actions'] ?? [])->contains(function (array $action): bool {
                if (($action['handler'] ?? null) !== 'enrollment.verify_payment') {
                    return false;
                }
                $actionConfiguration = is_array($action['configuration'] ?? null) ? $action['configuration'] : [];

                return ($actionConfiguration['receipt_mode'] ?? null) === 'none'
                    || ($actionConfiguration['allow_no_receipt'] ?? false) === true;
            }))
            ->pluck('key')
            ->all();
        foreach (data_get($configuration, 'workflow.steps', []) as $stepIndex => $step) {
            foreach ($step['transitions'] ?? [] as $transitionIndex => $transition) {
                if (in_array($transition['to'] ?? null, $noReceiptTargets, true)
                    && ($transition['requires_reason'] ?? false) !== true) {
                    throw ValidationException::withMessages([
                        "workflow.steps.{$stepIndex}.transitions.{$transitionIndex}.requires_reason" => 'No-receipt transitions must require an audited reason.',
                    ]);
                }
            }
        }

        $allowedPaymentMethods = data_get($configuration, 'billing.allowed_payment_methods', []);
        $supportedPaymentMethods = array_map(
            fn (PaymentMethod $method): string => $method->value,
            PaymentMethod::cases(),
        );
        if (! is_array($allowedPaymentMethods)
            || collect($allowedPaymentMethods)->contains(fn (mixed $method): bool => ! is_string($method) || ! in_array($method, $supportedPaymentMethods, true))) {
            throw ValidationException::withMessages(['billing.allowed_payment_methods' => 'One or more payment methods are not supported.']);
        }

        foreach ($configuration['notifications'] ?? [] as $index => $notification) {
            if (! is_array($notification) || ($notification['channel'] ?? null) !== 'mail') {
                throw ValidationException::withMessages(["notifications.{$index}.channel" => 'The core enrollment notification currently supports email only.']);
            }
            if (! in_array($notification['event'] ?? null, ['any_transition', 'completed', 'rejected', 'cancelled', ...$stepKeys], true)) {
                throw ValidationException::withMessages(["notifications.{$index}.event" => 'Choose a workflow step or the every-transition event.']);
            }
        }
    }

    private function validateItem(mixed $configuration, mixed $schema, string $path): void
    {
        if (! is_array($configuration)) {
            throw ValidationException::withMessages([$path => 'Configuration must be an object.']);
        }

        if (! is_array($schema)) {
            return;
        }

        foreach ($schema['fields'] ?? [] as $field) {
            $key = $field['key'] ?? null;
            $control = $field['control'] ?? null;
            if (! is_string($key) || ! is_string($control) || ! in_array($control, self::SupportedControls, true)) {
                throw ValidationException::withMessages([$path => 'The registered operator schema contains an unsupported field.']);
            }

            $optionSource = $field['option_source'] ?? null;
            if ($optionSource !== null && (! is_string($optionSource) || ! in_array($optionSource, self::SupportedOptionSources, true))) {
                throw ValidationException::withMessages(["{$path}.{$key}" => 'The registered option source is not allowed.']);
            }

            if (! $this->fieldIsVisible($field, $configuration)) {
                continue;
            }

            if (($field['required'] ?? false) === true
                && (! array_key_exists($key, $configuration) || $configuration[$key] === null || $configuration[$key] === '')) {
                throw ValidationException::withMessages(["{$path}.{$key}" => 'This setting is required.']);
            }
            if (! array_key_exists($key, $configuration)) {
                continue;
            }
            if ($configuration[$key] === null) {
                continue;
            }
            if ($configuration[$key] === '') {
                continue;
            }

            $value = $configuration[$key];
            $valid = match ($control) {
                'boolean' => is_bool($value),
                'number', 'money', 'percentage' => is_numeric($value),
                'multi_select', 'date_range' => is_array($value),
                default => is_string($value) || is_int($value),
            };

            if (! $valid) {
                throw ValidationException::withMessages(["{$path}.{$key}" => 'This setting has an invalid value.']);
            }

            if (isset($field['options']) && is_array($field['options']) && $field['options'] !== []) {
                $allowed = collect($field['options'])->pluck('value')->all();
                $values = is_array($value) ? $value : [$value];
                if (collect($values)->contains(fn (mixed $candidate): bool => ! in_array($candidate, $allowed, true))) {
                    throw ValidationException::withMessages(["{$path}.{$key}" => 'The selected value is not supported.']);
                }
            }

            if (is_numeric($value)) {
                $minimum = $field['min'] ?? $field['minimum'] ?? null;
                $maximum = $field['max'] ?? $field['maximum'] ?? null;
                if (is_numeric($minimum) && (float) $value < (float) $minimum) {
                    throw ValidationException::withMessages(["{$path}.{$key}" => "This setting must be at least {$minimum}."]);
                }
                if (is_numeric($maximum) && (float) $value > (float) $maximum) {
                    throw ValidationException::withMessages(["{$path}.{$key}" => "This setting may not be greater than {$maximum}."]);
                }
            }
        }
    }

    /** @param array<string, mixed> $field @param array<string, mixed> $configuration */
    private function fieldIsVisible(array $field, array $configuration): bool
    {
        $condition = $field['visible_when'] ?? null;
        if (! is_array($condition) || ! is_string($condition['field'] ?? null)) {
            return true;
        }

        $actual = $configuration[$condition['field']] ?? null;
        if (array_key_exists('equals', $condition)) {
            return $actual === $condition['equals'];
        }
        if (is_array($condition['in'] ?? null)) {
            return in_array($actual, $condition['in'], true);
        }

        return true;
    }
}
