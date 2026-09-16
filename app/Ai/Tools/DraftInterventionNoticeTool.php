<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class DraftInterventionNoticeTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Draft an empathetic, constructive intervention letter or email to an at-risk student or the Guidance Office with proposed makeup activities.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'course_title' => 'required|string',
            'reason' => 'required|string',
        ]);

        $student = Student::query()->find($validated['student_id']);
        $name = $student ? "{$student->first_name} {$student->last_name}" : 'Student';

        return json_encode([
            'recipient' => $name,
            'course' => $validated['course_title'],
            'subject' => "Academic Check-in & Support: {$validated['course_title']}",
            'body' => "Dear {$name},\n\nI am reaching out to check in on your progress in {$validated['course_title']}. We noticed recent challenges regarding {$validated['reason']}. Our goal is to ensure you have all the resources necessary to succeed.\n\nPlease visit during my upcoming office hours or respond to this notice so we can review makeup opportunities and tailored support.\n\nWarm regards,\nFaculty Academic Team",
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
            'course_title' => $schema->string()->required(),
            'reason' => $schema->string()->required(),
        ];
    }
}
