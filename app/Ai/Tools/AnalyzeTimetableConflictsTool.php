<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Services\TimetableConflictService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class AnalyzeTimetableConflictsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Analyze a list of class section IDs for timetable conflicts, overlapping schedules, and room assignment collisions.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'class_ids' => 'required|array',
            'class_ids.*' => 'integer',
        ]);

        $classes = Classes::query()
            ->with(['schedules', 'Room'])
            ->whereIn('id', $validated['class_ids'])
            ->get();

        if ($classes->isEmpty()) {
            return 'No valid classes found matching the provided IDs.';
        }

        $conflictService = app(TimetableConflictService::class);
        $conflicts = [];

        foreach ($classes as $i => $classA) {
            foreach ($classes as $j => $classB) {
                if ($i >= $j) {
                    continue;
                }

                $hasConflict = $conflictService->hasScheduleConflict($classA, $classB);
                if ($hasConflict) {
                    $conflicts[] = [
                        'class_a' => $classA->class_subject_title ?? "Class #{$classA->id}",
                        'class_b' => $classB->class_subject_title ?? "Class #{$classB->id}",
                        'conflict_type' => 'Time overlap on overlapping day',
                    ];
                }
            }
        }

        return json_encode([
            'total_classes_analyzed' => $classes->count(),
            'conflict_count' => count($conflicts),
            'has_clash' => count($conflicts) > 0,
            'conflicts' => $conflicts,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_ids' => $schema->array()->items($schema->integer())->required(),
        ];
    }
}
