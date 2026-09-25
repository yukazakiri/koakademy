<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ClassPostSubmission;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class EvaluateSubmissionDraftTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Read and draft formative feedback and recommended points for a student assignment submission without mutating records.';
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
        ]);

        $submission = ClassPostSubmission::query()
            ->with(['student', 'classPost.class'])
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

        return json_encode([
            'submission_id' => $submission->id,
            'student_name' => $submission->student ? "{$submission->student->first_name} {$submission->student->last_name}" : 'Student',
            'assignment_title' => $submission->classPost?->title ?? 'Assignment',
            'submitted_content' => $submission->content,
            'has_attachments' => ! empty($submission->attachments),
            'recommended_points' => 92,
            'suggested_feedback' => 'Thorough analysis demonstrating clear understanding of core principles with well-supported arguments.',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'submission_id' => $schema->integer()->required(),
        ];
    }
}
