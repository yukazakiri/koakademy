<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ClassPostSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class CommitSubmissionGradeTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Publish official points and instructor feedback to a student submission. Requires faculty approval prior to saving.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'points' => 'required|integer|min:0',
            'feedback' => 'required|string',
        ]);

        $submission = ClassPostSubmission::query()->find($validated['submission_id']);
        if (! $submission instanceof ClassPostSubmission) {
            return "Submission #{$validated['submission_id']} was not found.";
        }

        $submission->update([
            'points' => $validated['points'],
            'feedback' => $validated['feedback'],
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        return json_encode([
            'success' => true,
            'submission_id' => $submission->id,
            'points' => $submission->points,
            'status' => 'graded',
            'message' => 'Grade and feedback successfully committed.',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'submission_id' => $schema->integer()->required(),
            'points' => $schema->integer()->required(),
            'feedback' => $schema->string()->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required(
            "Confirming will assign {$request['points']} points and publish final feedback directly to the student portal."
        );
    }
}
