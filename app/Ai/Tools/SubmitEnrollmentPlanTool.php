<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Models\Student;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class SubmitEnrollmentPlanTool implements Approvable, Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Officially submit the student enrollment course plan to the Registrar for review and registration. Requires student confirmation before submission.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
            'class_ids' => 'required|array',
            'class_ids.*' => 'integer',
        ]);

        $student = Student::query()->find($validated['student_id']);
        if (! $student instanceof Student) {
            return "Student ID {$validated['student_id']} not found.";
        }

        $classes = Classes::query()->whereIn('id', $validated['class_ids'])->get();

        return json_encode([
            'status' => 'submitted',
            'student_id' => $student->id,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'enrolled_class_count' => $classes->count(),
            'message' => 'Enrollment plan has been officially submitted and queued for Registrar evaluation.',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
            'class_ids' => $schema->array()->items($schema->integer())->required(),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $classCount = count($request['class_ids'] ?? []);

        return Approval::required(
            "Submitting this enrollment plan will officially register {$classCount} courses for registrar assessment and billing."
        );
    }
}
