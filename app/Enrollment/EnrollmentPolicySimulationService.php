<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Data\Enrollment\CompiledEnrollmentPolicy;
use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\RuleResult;

final readonly class EnrollmentPolicySimulationService
{
    public function __construct(
        private EnrollmentPolicyResolver $resolver,
        private EnrollmentPolicyRegistry $registry,
    ) {}

    /** @return array<string, mixed> */
    public function simulate(EnrollmentContext $context): array
    {
        return $this->simulateCompiled($this->resolver->resolve($context), $context);
    }

    /** @return array<string, mixed> */
    public function simulateCompiled(CompiledEnrollmentPolicy $compiled, EnrollmentContext $context): array
    {
        $results = [];
        $blockers = [];
        $manifest = $this->registry->manifest();

        foreach ($compiled->configuration['rules'] ?? [] as $rule) {
            $handler = (string) $rule['handler'];
            $result = EnrollmentRuleTiming::appliesAtCompletion($handler)
                ? RuleResult::pass(['deferred' => true, 'enforcement' => 'terminal_transition'])
                : $this->registry->rule($handler)->evaluate($context, $rule['configuration'] ?? []);
            $category = (string) data_get($manifest, "rules.{$rule['handler']}.category", 'eligibility');
            $row = [
                'key' => $rule['key'],
                'handler' => $rule['handler'],
                'label' => (string) data_get($manifest, "rules.{$rule['handler']}.label", $rule['key']),
                'section' => $category === 'availability' ? 'eligibility' : ($category === 'billing' ? 'billing' : 'eligibility'),
                'passed' => $result->passed,
                'message' => $result->message,
                'metadata' => $result->metadata,
            ];
            $results[] = $row;
            if (! $result->passed) {
                $blockers[] = $row;
            }
        }

        $entry = collect(data_get($compiled->configuration, 'workflow.steps', []))->firstWhere('entry', true);
        $steps = collect(data_get($compiled->configuration, 'workflow.steps', []))->keyBy('key');
        $route = [];
        $current = $entry;
        while (is_array($current) && count($route) < $steps->count()) {
            $route[] = [
                'key' => $current['key'],
                'label' => $current['label'],
                'terminal' => (bool) ($current['terminal'] ?? false),
            ];
            if (($current['terminal'] ?? false) === true) {
                break;
            }
            $selected = collect($current['transitions'] ?? [])->first(fn (array $transition): bool => ($transition['fallback'] ?? false) === true
                || collect($transition['conditions'] ?? [])->every(fn (array $condition): bool => $this->registry->rule($condition['handler'])->evaluate($context, $condition['configuration'] ?? [])->passed));
            $current = is_array($selected) ? $steps->get($selected['to']) : null;
        }
        $assignmentKey = data_get($compiled->configuration, 'assignment.strategy');
        $billingKey = data_get($compiled->configuration, 'billing.strategy');

        return [
            'writes_performed' => false,
            'actions_executed' => false,
            'route_basis' => 'Current sample facts only; staff permissions, requirements, action payloads, and capacity are enforced at runtime.',
            'checksum' => $compiled->checksum,
            'source_version_ids' => $compiled->sourceVersionIds,
            'matched_policies' => $compiled->sourceLayers,
            'source_map' => $compiled->sourceMap,
            'eligibility' => $results,
            'blockers' => $blockers,
            'entry_step' => $entry,
            'workflow_route' => $route,
            'assignment' => is_string($assignmentKey)
                ? $this->registry->assignmentStrategy($assignmentKey)->recommend($context, data_get($compiled->configuration, 'assignment.configuration', []))
                : null,
            'billing' => is_string($billingKey)
                ? $this->registry->billingStrategy($billingKey)->calculate($context, data_get($compiled->configuration, 'billing.configuration', []))
                : null,
            'notifications' => collect($compiled->configuration['notifications'] ?? [])->where('enabled', '!=', false)->values()->all(),
        ];
    }
}
