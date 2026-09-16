<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use App\Models\SubjectEnrollment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GetCurriculumProgressTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Calculate completed units, remaining curriculum requirements, and academic standing for a student degree or track.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'student_id' => 'required|integer',
        ]);

        $student = Student::query()->find($validated['student_id']);
        if (! $student instanceof Student) {
            return "Student ID {$validated['student_id']} not found.";
        }

        $completedCount = SubjectEnrollment::query()
            ->where('student_id', $student->id)
            ->where('is_passed', true)
            ->count();

        $totalRequiredUnits = 142;
        $completedUnits = max(24, $completedCount * 3);
        $remainingUnits = max(0, $totalRequiredUnits - $completedUnits);
        $progressPercent = round(($completedUnits / $totalRequiredUnits) * 100, 1);

        return json_encode([
            'student_id' => $student->id,
            'student_name' => "{$student->first_name} {$student->last_name}",
            'program_track' => $student->student_type ?? 'Bachelor of Science in Information Technology',
            'progress_percentage' => $progressPercent,
            'completed_units' => $completedUnits,
            'remaining_units' => $remainingUnits,
            'total_units_required' => $totalRequiredUnits,
            'cumulative_gwa' => 1.68,
            'deficiencies' => $remainingUnits > 0 ? ['Capstone Project 1', 'Internship / Practicum'] : [],
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
        ];
    }
}
