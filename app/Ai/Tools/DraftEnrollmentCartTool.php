<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Models\Student;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class DraftEnrollmentCartTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Stage a preliminary schedule and enrollment cart for a student before final submission and human sign-off.';
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

        $classes = Classes::query()
            ->with(['schedules', 'Room'])
            ->whereIn('id', $validated['class_ids'])
            ->get();

        $totalUnits = $classes->count() * 3;

        return json_encode([
            'status' => 'staged_in_cart',
            'student_id' => $student->id,
            'classes_count' => $classes->count(),
            'total_units' => $totalUnits,
            'staged_classes' => $classes->map(fn ($c) => [
                'class_id' => $c->id,
                'title' => $c->class_subject_title ?? "Class #{$c->id}",
                'faculty' => $c->faculty_full_name ?? 'Staff Instructor',
                'days' => $c->schedule_days ?? 'TBA',
            ])->values(),
            'next_step' => 'Present this staged cart to the student. If confirmed, invoke SubmitEnrollmentPlanTool to register.',
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->required(),
            'class_ids' => $schema->array()->items($schema->integer())->required(),
        ];
    }
}
