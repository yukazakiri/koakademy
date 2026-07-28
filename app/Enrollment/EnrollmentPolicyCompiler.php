<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Contracts\Enrollment\RuntimeEnrollmentAssignmentStrategy;
use App\Contracts\Enrollment\RuntimeEnrollmentBillingStrategy;
use App\Data\Enrollment\CompiledEnrollmentPolicy;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final readonly class EnrollmentPolicyCompiler
{
    public const int CurrentSchemaVersion = 1;

    public function __construct(
        private EnrollmentPolicyRegistry $registry,
        private EnrollmentConfigurationValidator $configurationValidator,
    ) {}

    /**
     * @param  array<int, array{version_id:int, configuration:array<string, mixed>, policy_id?:int, policy_name?:string, version?:int, scope?:array<string, string>}>  $layers
     */
    public function compile(array $layers): CompiledEnrollmentPolicy
    {
        if ($layers === []) {
            throw ValidationException::withMessages(['policy' => 'No published enrollment policy matches this context.']);
        }

        $resolved = [];
        $sourceVersionIds = [];
        $sourceLayers = [];
        $sourceMap = [];

        foreach ($layers as $layer) {
            $sourceVersionIds[] = $layer['version_id'];
            $source = $this->sourceDescriptor($layer);
            $sourceLayers[] = $source;
            $this->recordSources($sourceMap, $layer['configuration'], $source);
            $resolved = $this->merge($resolved, $layer['configuration']);
        }

        $schemaVersion = (int) ($resolved['schema_version'] ?? self::CurrentSchemaVersion);
        if ($schemaVersion !== self::CurrentSchemaVersion) {
            throw ValidationException::withMessages(['schema_version' => "Unsupported policy schema version [{$schemaVersion}]."]);
        }

        $resolved = $this->normalizeDefaults($resolved);
        $this->validateHandlers($resolved);
        $this->configurationValidator->validate($resolved);
        $this->rejectExecutableConfiguration($resolved);
        $this->validateWorkflow($resolved['workflow'] ?? null);

        $canonical = $this->canonicalize($resolved);

        return new CompiledEnrollmentPolicy(
            schemaVersion: $schemaVersion,
            checksum: $this->checksumConfiguration($canonical),
            configuration: $canonical,
            sourceVersionIds: array_values(array_unique($sourceVersionIds)),
            sourceLayers: $sourceLayers,
            sourceMap: $sourceMap,
        );
    }

    /** @param array<string, mixed> $configuration */
    public function checksumConfiguration(array $configuration): string
    {
        $json = json_encode(
            $this->canonicalize($configuration),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        return hash('sha256', $json);
    }

    /** @param array<string, mixed> $base @param array<string, mixed> $override @return array<string, mixed> */
    private function merge(array $base, array $override): array
    {
        foreach (['rules', 'requirements'] as $key) {
            if (array_key_exists($key, $override)) {
                $base[$key] = $this->mergeKeyedEntries($base[$key] ?? [], $override[$key]);
                unset($override[$key]);
            }
        }

        foreach (['workflow', 'assignment', 'billing', 'notifications'] as $atomicKey) {
            if (array_key_exists($atomicKey, $override)) {
                $base[$atomicKey] = $override[$atomicKey];
                unset($override[$atomicKey]);
            }
        }

        return array_replace_recursive($base, $override);
    }

    /** @return array<int, array<string, mixed>> */
    private function mergeKeyedEntries(mixed $inherited, mixed $overrides): array
    {
        $entries = collect(is_array($inherited) ? $inherited : [])->keyBy('key');

        foreach (is_array($overrides) ? $overrides : [] as $entry) {
            if (! is_array($entry) || ! is_string($entry['key'] ?? null)) {
                throw ValidationException::withMessages(['configuration' => 'Rules and requirements must have stable keys.']);
            }

            if (($entry['enabled'] ?? true) === false) {
                $entries->forget($entry['key']);
            } else {
                $entries->put($entry['key'], array_replace_recursive($entries->get($entry['key'], []), $entry));
            }
        }

        return $entries->values()->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $sourceMap
     * @param  array<string, mixed>  $configuration
     * @param  array<string, mixed>  $source
     */
    private function recordSources(array &$sourceMap, array $configuration, array $source): void
    {
        foreach (['rules', 'requirements'] as $collectionKey) {
            foreach ($configuration[$collectionKey] ?? [] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if (! is_string($entry['key'] ?? null)) {
                    continue;
                }
                $path = "{$collectionKey}.{$entry['key']}";
                if (($entry['enabled'] ?? true) === false) {
                    unset($sourceMap[$path]);

                    continue;
                }

                $sourceMap[$path] = $source;
            }
        }

        foreach (['workflow', 'assignment', 'billing', 'notifications'] as $atomicKey) {
            if (array_key_exists($atomicKey, $configuration)) {
                $sourceMap[$atomicKey] = $source;
            }
        }
    }

    /**
     * @param  array{version_id:int, configuration:array<string, mixed>, policy_id?:int, policy_name?:string, version?:int, scope?:array<string, string>}  $layer
     * @return array<string, mixed>
     */
    private function sourceDescriptor(array $layer): array
    {
        return array_filter([
            'policy_id' => $layer['policy_id'] ?? null,
            'policy_name' => $layer['policy_name'] ?? null,
            'version_id' => $layer['version_id'],
            'version' => $layer['version'] ?? null,
            'scope' => $layer['scope'] ?? [],
        ], fn (mixed $value): bool => $value !== null);
    }

    /** @param array<string, mixed> $configuration */
    private function validateHandlers(array $configuration): void
    {
        foreach ($configuration['rules'] ?? [] as $index => $rule) {
            $handler = $rule['handler'] ?? null;
            if (! is_string($handler) || ! $this->registry->hasRule($handler)) {
                throw ValidationException::withMessages(["rules.{$index}.handler" => "Unknown enrollment rule handler [{$handler}]."]);
            }
        }

        foreach (data_get($configuration, 'workflow.steps', []) as $stepIndex => $step) {
            foreach ($step['actions'] ?? [] as $actionIndex => $action) {
                $handler = $action['handler'] ?? null;
                if (! is_string($handler) || ! $this->registry->hasAction($handler)) {
                    throw ValidationException::withMessages(["workflow.steps.{$stepIndex}.actions.{$actionIndex}.handler" => "Unknown enrollment action handler [{$handler}]."]);
                }
            }

            foreach ($step['transitions'] ?? [] as $transitionIndex => $transition) {
                foreach ($transition['conditions'] ?? [] as $conditionIndex => $condition) {
                    $handler = $condition['handler'] ?? null;
                    if (! is_string($handler) || ! $this->registry->hasRule($handler)) {
                        throw ValidationException::withMessages(["workflow.steps.{$stepIndex}.transitions.{$transitionIndex}.conditions.{$conditionIndex}.handler" => "Unknown enrollment rule handler [{$handler}]."]);
                    }
                }
            }
        }

        $assignment = data_get($configuration, 'assignment.strategy');
        if (is_string($assignment) && ! $this->registry->hasAssignmentStrategy($assignment)) {
            throw ValidationException::withMessages(['assignment.strategy' => "Unknown enrollment assignment strategy [{$assignment}]."]);
        }
        if (is_string($assignment) && ! ($this->registry->assignmentStrategy($assignment) instanceof RuntimeEnrollmentAssignmentStrategy)) {
            throw ValidationException::withMessages(['assignment.strategy' => "Enrollment assignment strategy [{$assignment}] cannot execute at runtime."]);
        }

        $billing = data_get($configuration, 'billing.strategy');
        if (is_string($billing) && ! $this->registry->hasBillingStrategy($billing)) {
            throw ValidationException::withMessages(['billing.strategy' => "Unknown enrollment billing strategy [{$billing}]."]);
        }
        if (is_string($billing) && ! ($this->registry->billingStrategy($billing) instanceof RuntimeEnrollmentBillingStrategy)) {
            throw ValidationException::withMessages(['billing.strategy' => "Enrollment billing strategy [{$billing}] cannot execute at runtime."]);
        }
    }

    /** @param array<string, mixed> $configuration @return array<string, mixed> */
    private function normalizeDefaults(array $configuration): array
    {
        $configuration['requirements'] = array_values(array_map(function (mixed $requirement): mixed {
            if (! is_array($requirement)) {
                return $requirement;
            }

            return [
                'required' => true,
                'enabled' => true,
                'enforcement_step' => null,
                ...$requirement,
            ];
        }, $configuration['requirements'] ?? []));

        $configuration['assignment'] = [
            'strategy' => 'assignment.manual',
            'configuration' => [],
            ...($configuration['assignment'] ?? []),
        ];

        $billing = is_array($configuration['billing'] ?? null) ? $configuration['billing'] : [];
        $billingConfiguration = is_array($billing['configuration'] ?? null) ? $billing['configuration'] : [];
        $minimum = array_key_exists('minimum_payment_type', $billingConfiguration)
            ? [
                'type' => $billingConfiguration['minimum_payment_type'] ?? 'none',
                'value' => $billingConfiguration['minimum_payment_value'] ?? 0,
            ]
            : (is_array($billingConfiguration['minimum_payment'] ?? null)
            ? $billingConfiguration['minimum_payment']
            : [
                'type' => $billingConfiguration['minimum_payment_type'] ?? 'none',
                'value' => $billingConfiguration['minimum_payment_value'] ?? 0,
            ]);
        $configuration['billing'] = [
            'strategy' => 'billing.course_rate',
            ...$billing,
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
                ...$billingConfiguration,
                'minimum_payment' => [
                    'type' => (string) ($minimum['type'] ?? 'none'),
                    'value' => (float) ($minimum['value'] ?? 0),
                ],
            ],
            'allowed_payment_methods' => is_array($billing['allowed_payment_methods'] ?? null)
                ? array_values($billing['allowed_payment_methods'])
                : $billing['allowed_payment_methods'] ?? [],
        ];

        $defaultReceiptMode = (string) data_get($configuration, 'billing.configuration.receipt_mode', 'required');
        $configuration['workflow']['steps'] = array_values(array_map(function (mixed $step) use ($defaultReceiptMode): mixed {
            if (! is_array($step)) {
                return $step;
            }
            $step['actions'] = array_values(array_map(
                function (mixed $action) use ($defaultReceiptMode): mixed {
                    if (! is_array($action)) {
                        return $action;
                    }

                    $handler = $action['handler'] ?? null;
                    $actionConfiguration = is_array($action['configuration'] ?? null)
                        ? $action['configuration']
                        : [];
                    $action['configuration'] = match ($handler) {
                        'enrollment.verify_payment' => [
                            'receipt_mode' => $defaultReceiptMode,
                            'record_transaction' => true,
                            'allow_no_receipt' => $defaultReceiptMode !== 'required',
                            ...$actionConfiguration,
                        ],
                        'enrollment.assign_classes' => [
                            'mode' => 'runtime_payload',
                            ...$actionConfiguration,
                        ],
                        'enrollment.generate_assessment' => [
                            'create_new_file' => false,
                            ...$actionConfiguration,
                        ],
                        'enrollment.notify' => [
                            'notification' => 'assessment',
                            ...$actionConfiguration,
                        ],
                        default => $actionConfiguration,
                    };

                    return $action;
                },
                is_array($step['actions'] ?? null) ? $step['actions'] : [],
            ));
            $step['transitions'] = array_values(array_map(
                fn (mixed $transition): mixed => is_array($transition)
                    ? ['requires_reason' => false, ...$transition]
                    : $transition,
                $step['transitions'] ?? [],
            ));

            return $step;
        }, data_get($configuration, 'workflow.steps', [])));
        $configuration['notifications'] = array_values($configuration['notifications'] ?? []);

        return $configuration;
    }

    private function validateWorkflow(mixed $workflow): void
    {
        if (! is_array($workflow) || ! is_array($workflow['steps'] ?? null) || $workflow['steps'] === []) {
            throw ValidationException::withMessages(['workflow' => 'A workflow with at least one step is required.']);
        }

        $steps = collect($workflow['steps']);
        $keys = $steps->pluck('key');
        if ($keys->contains(null) || $keys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['workflow.steps' => 'Workflow step keys must be present and unique.']);
        }

        $entrySteps = $steps->where('entry', true);
        if ($entrySteps->count() !== 1) {
            throw ValidationException::withMessages(['workflow.steps' => 'A workflow must have exactly one entry step.']);
        }

        if ($steps->where('terminal', true)->isEmpty()) {
            throw ValidationException::withMessages(['workflow.steps' => 'A workflow must have at least one terminal step.']);
        }

        $adjacency = [];
        foreach ($steps as $index => $step) {
            $key = (string) $step['key'];
            $transitions = $step['transitions'] ?? [];
            $adjacency[$key] = [];

            if (($step['terminal'] ?? false) === true) {
                if ($transitions !== []) {
                    throw ValidationException::withMessages(["workflow.steps.{$index}.transitions" => 'Terminal steps cannot have outgoing transitions.']);
                }

                continue;
            }

            $fallbacks = collect($transitions)->where('fallback', true);
            if ($fallbacks->count() !== 1) {
                throw ValidationException::withMessages(["workflow.steps.{$index}.transitions" => 'Each non-terminal step must have exactly one fallback transition.']);
            }

            if ((array_last($transitions)['fallback'] ?? false) !== true) {
                throw ValidationException::withMessages(["workflow.steps.{$index}.transitions" => 'The Otherwise transition must be last.']);
            }

            foreach ($transitions as $transitionIndex => $transition) {
                $target = $transition['to'] ?? null;
                if (! is_string($target) || ! $keys->contains($target)) {
                    throw ValidationException::withMessages(["workflow.steps.{$index}.transitions.{$transitionIndex}.to" => 'Transition target does not exist.']);
                }
                if ($target === $key) {
                    throw ValidationException::withMessages(["workflow.steps.{$index}.transitions.{$transitionIndex}.to" => 'Self-links are not supported.']);
                }
                $adjacency[$key][] = $target;
            }
        }

        $entryKey = (string) $entrySteps->first()['key'];
        $visited = [];
        $visiting = [];
        $walk = function (string $key) use (&$walk, &$visited, &$visiting, $adjacency): void {
            if (isset($visiting[$key])) {
                throw ValidationException::withMessages(['workflow.steps' => 'Workflow cycles are not supported.']);
            }
            if (isset($visited[$key])) {
                return;
            }
            $visiting[$key] = true;
            foreach ($adjacency[$key] as $target) {
                $walk($target);
            }
            unset($visiting[$key]);
            $visited[$key] = true;
        };
        $walk($entryKey);

        if (count($visited) !== $steps->count()) {
            throw ValidationException::withMessages(['workflow.steps' => 'All workflow steps must be reachable from the entry step.']);
        }
    }

    /** @param array<string, mixed> $configuration */
    private function rejectExecutableConfiguration(array $configuration): void
    {
        $walk = function (mixed $value, string $path = 'configuration') use (&$walk): void {
            if (! is_array($value)) {
                if (is_string($value) && (str_contains($value, '<?php') || str_contains($value, 'function('))) {
                    throw ValidationException::withMessages([$path => 'Executable code is not allowed in enrollment policy configuration.']);
                }
                if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false) {
                    throw ValidationException::withMessages([$path => 'Arbitrary URLs are not allowed in enrollment policy configuration.']);
                }

                return;
            }

            foreach ($value as $key => $nested) {
                $keyName = is_string($key) ? mb_strtolower($key) : (string) $key;
                if (in_array($keyName, ['class', 'class_name', 'script', 'code'], true)) {
                    throw ValidationException::withMessages(["{$path}.{$key}" => 'Executable classes and scripts cannot be imported.']);
                }
                $walk($nested, "{$path}.{$key}");
            }
        };

        $walk($configuration);
    }

    /** @return array<string, mixed> */
    private function canonicalize(array $configuration): array
    {
        $sort = function (mixed $value) use (&$sort): mixed {
            if (! is_array($value)) {
                return $value;
            }
            if (Arr::isAssoc($value)) {
                ksort($value);
            }

            return array_map($sort, $value);
        };

        return $sort($configuration);
    }
}
