<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class SimulatePolicyImpactTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Report whether a proposed enrollment or admissions policy can be simulated with supported institutional records. Eligibility and retention projections are unavailable without a defined cohort, policy rules, and a validated impact model.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'policy_id' => 'sometimes|integer|min:1',
            'minimum_gwa_threshold' => 'required|numeric',
            'max_units_allowed' => 'required|integer|min:1',
        ]);

        return json_encode([
            'status' => 'unavailable',
            'available' => false,
            'reason' => 'policy_impact_model_unavailable',
            'message' => 'No simulation was performed. This tool does not have a supported policy-evaluation model defining the academic period, cohort, GWA scale and comparison direction, unit-load basis, or retention effects.',
            'simulation_id' => null,
            'policy_id' => $validated['policy_id'] ?? null,
            'minimum_gwa_threshold' => $validated['minimum_gwa_threshold'],
            'max_units_allowed' => $validated['max_units_allowed'],
            'cohort_size' => null,
            'projected_eligible_count' => null,
            'projected_restricted_count' => null,
            'retention_impact_percentage' => null,
            'recommendation' => null,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'policy_id' => $schema->integer(),
            'minimum_gwa_threshold' => $schema->number()->required(),
            'max_units_allowed' => $schema->integer()->required(),
        ];
    }
}
