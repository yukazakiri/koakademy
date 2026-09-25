<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\StudentClearance;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class BatchUpdateClearanceTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Sign off and resolve academic clearance holds for a batch of students. Requires registrar supervisor confirmation before committing.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        
        if (! $user || (! $user->hasRole('super_admin') && ! $user->can('manage_clearance'))) {
            return json_encode([
                'error' => true,
                'message' => 'Unauthorized: This tool requires the manage_clearance permission.',
            ], JSON_PRETTY_PRINT);
        }

        $validated = $request->validate([
            'clearance_ids' => 'required|array',
            'clearance_ids.*' => 'integer',
            'remarks' => 'nullable|string|max:500',
        ]);

        $updatedCount = StudentClearance::query()
            ->whereIn('id', $validated['clearance_ids'])
            ->update([
                'is_cleared' => true,
                'cleared_at' => now(),
                'remarks' => $validated['remarks'] ?? 'Cleared via AI Registrar Auditor upon supervisor verification.',
            ]);

        return json_encode([
            'success' => true,
            'cleared_records_count' => $updatedCount,
            'message' => "Successfully resolved {$updatedCount} clearance holds.",
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'clearance_ids' => $schema->array()->items($schema->integer())->required(),
            'remarks' => $schema->string(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $count = count($request['clearance_ids'] ?? []);

        return Approval::required(
            "Signing off on {$count} clearance records will release administrative holds and permit students to register or receive official diplomas."
        );
    }
}
