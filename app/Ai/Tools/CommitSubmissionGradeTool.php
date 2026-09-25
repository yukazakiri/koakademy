<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ClassPostSubmission;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
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
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required.'], JSON_PRETTY_PRINT);
        }

        $faculty = Faculty::query()->where('user_id', $user->id)->first();
        if (! $faculty instanceof Faculty) {
            return json_encode(['error' => true, 'message' => 'Faculty record not found for this account.'], JSON_PRETTY_PRINT);
        }

        $validated = $request->validate([
            'submission_id' => 'required|integer',
            'points' => 'required|integer|min:0',
            'feedback' => 'required|string',
        ]);

        $submission = ClassPostSubmission::query()
            ->with(['classPost.class'])
            ->find($validated['submission_id']);

        if (! $submission instanceof ClassPostSubmission) {
            return "Submission #{$validated['submission_id']} was not found.";
        }

        $class = $submission->classPost?->class;
        if (! $class || $class->faculty_id !== $faculty->id) {
            return json_encode([
                'error' => true,
                'message' => 'Access denied. This submission does not belong to a class you are teaching.',
            ], JSON_PRETTY_PRINT);
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
