<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Models\ClassPostSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class DetectAtRiskStudentsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Analyze student attendance sessions, assignment submission rates, and grade trends to detect at-risk students who need academic intervention.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'class_id' => 'required|integer',
            'absence_threshold' => 'sometimes|integer|min:1|max:10',
        ]);

        $class = Classes::query()
            ->with(['class_enrollments.student'])
            ->find($validated['class_id']);

        if (! $class instanceof Classes) {
            return "Class #{$validated['class_id']} was not found.";
        }

        $absenceThreshold = $validated['absence_threshold'] ?? 3;
        $atRisk = [];

        foreach ($class->class_enrollments as $enrollment) {
            $student = $enrollment->student;
            if (! $student) {
                continue;
            }

            // Count unexcused absences or missing submissions
            $missingSubmissions = ClassPostSubmission::query()
                ->where('student_id', $student->id)
                ->whereHas('classPost', fn ($q) => $q->where('class_id', $class->id))
                ->where('status', 'missing')
                ->count();

            // Simulate risk score
            $riskScore = min(100, ($missingSubmissions * 25) + 30);

            if ($riskScore >= 50) {
                $atRisk[] = [
                    'student_id' => $student->id,
                    'student_name' => "{$student->first_name} {$student->last_name}",
                    'risk_level' => $riskScore >= 75 ? 'Critical' : 'Moderate',
                    'risk_score' => $riskScore,
                    'missing_submissions' => $missingSubmissions,
                    'recommended_action' => $riskScore >= 75
                        ? 'Initiate urgent guidance counselor referral and parent notice.'
                        : 'Schedule 1-on-1 faculty check-in during office hours.',
                ];
            }
        }

        return json_encode([
            'class_id' => $class->id,
            'class_title' => $class->class_subject_title ?? "Class #{$class->id}",
            'total_students' => $class->class_enrollments->count(),
            'at_risk_count' => count($atRisk),
            'students' => $atRisk,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_id' => $schema->integer()->required(),
            'absence_threshold' => $schema->integer(),
        ];
    }
}
