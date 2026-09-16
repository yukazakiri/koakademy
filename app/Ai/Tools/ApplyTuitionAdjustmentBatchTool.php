<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\TuitionAdjustment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class ApplyTuitionAdjustmentBatchTool implements Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Apply and commit tuition adjustment entries to student ledgers. Requires explicit bursar supervisor approval.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'adjustments' => 'required|array',
            'adjustments.*.student_id' => 'required|integer',
            'adjustments.*.amount' => 'required|numeric',
            'adjustments.*.reason' => 'required|string',
            'justification' => 'required|string',
        ]);

        $applied = 0;

        foreach ($validated['adjustments'] as $adj) {
            TuitionAdjustment::query()->create([
                'reason' => $adj['reason'],
                'source' => 'ai_bursar_adjustment',
                'idempotency_key' => 'ai_adj_'.bin2hex(random_bytes(8)),
            ]);
            $applied++;
        }

        return json_encode([
            'success' => true,
            'committed_count' => $applied,
            'message' => "Successfully posted {$applied} ledger adjustments with supervisor authorization.",
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'adjustments' => $schema->array()->items(
                $schema->object(fn ($s) => [
                    'student_id' => $s->integer()->required(),
                    'amount' => $s->number()->required(),
                    'reason' => $s->string()->required(),
                ])
            )->required(),
            'justification' => $schema->string()->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $batchCount = count($request['adjustments'] ?? []);

        return Approval::required(
            "Committing {$batchCount} financial adjustments will mutate official student ledger balances and generate immutable accounting audit trails."
        );
    }
}
