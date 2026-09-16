<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class SimulateScholarshipAdjustmentTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Simulate the net fee reduction and discount percentage for a student under different institutional scholarship programs or government grants.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'scholarship_type' => 'required|string',
            'base_tuition' => 'required|numeric',
        ]);

        $baseTuition = (float) $validated['base_tuition'];
        $type = $validated['scholarship_type'];

        $discountPercent = match (mb_strtolower($type)) {
            'academic_full', 'full' => 100,
            'academic_half', 'half' => 50,
            'athletic' => 75,
            'tesda_grant', 'ched_grant' => 100,
            default => 25,
        };

        $discountAmount = ($baseTuition * $discountPercent) / 100;
        $netTuition = max(0, $baseTuition - $discountAmount);

        return json_encode([
            'scholarship_program' => $type,
            'base_tuition' => $baseTuition,
            'discount_percentage' => $discountPercent,
            'discount_amount' => $discountAmount,
            'net_tuition_due' => $netTuition,
            'requires_special_clearance' => $discountPercent === 100,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scholarship_type' => $schema->string()->required(),
            'base_tuition' => $schema->number()->required(),
        ];
    }
}
