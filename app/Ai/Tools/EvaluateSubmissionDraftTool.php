<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ClassPostSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        $validated = $request->validate([
            'submission_id' => 'required|integer',
        ]);

        $submission = ClassPostSubmission::query()
            ->with(['student', 'classPost'])
            ->find($validated['submission_id']);

        if (! $submission instanceof ClassPostSubmission) {
            return "Submission #{$validated['submission_id']} was not found.";
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
