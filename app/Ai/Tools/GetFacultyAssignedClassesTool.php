<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Models\Faculty;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GetFacultyAssignedClassesTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieve all assigned classes, sections, schedules, and total student counts for a specific faculty member or instructor.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'faculty_id' => 'nullable|integer',
            'faculty_name' => 'nullable|string',
        ]);

        $facultyQuery = Faculty::query()->with([
            'classes.schedules',
            'classes.room',
            'classes.class_enrollments',
        ]);

        if (filled($validated['faculty_id'] ?? null)) {
            $faculty = $facultyQuery->find($validated['faculty_id']);
        } elseif (filled($validated['faculty_name'] ?? null)) {
            $name = $validated['faculty_name'];
            $faculty = $facultyQuery->where(function ($q) use ($name) {
                $q->where('first_name', 'like', "%{$name}%")
                    ->orWhere('last_name', 'like', "%{$name}%")
                    ->orWhere('email', 'like', "%{$name}%");
            })->first();
        } else {
            return 'Please specify either a faculty_id or faculty_name to query teaching assignments.';
        }

        if (! $faculty instanceof Faculty) {
            $target = $validated['faculty_name'] ?? ($validated['faculty_id'] ?? 'Faculty');

            return "Faculty member '{$target}' was not found in records.";
        }

        $classes = $faculty->classes->map(function (Classes $class) {
            $schedules = $class->schedules->map(fn ($s) => "{$s->day_of_week} {$s->formatted_start_time}-{$s->formatted_end_time}")->implode(', ');

            return [
                'class_id' => $class->id,
                'subject_code' => $class->subject_code,
                'section' => $class->section,
                'room' => $class->room?->name ?? 'TBA',
                'schedule' => $schedules ?: 'TBA',
                'school_year' => $class->school_year,
                'semester' => $class->semester,
                'enrolled_students' => $class->class_enrollments->count(),
                'max_slots' => $class->maximum_slots,
            ];
        })->values()->all();

        $totalStudents = array_sum(array_column($classes, 'enrolled_students'));

        return json_encode([
            'faculty_id' => $faculty->id,
            'faculty_name' => $faculty->name,
            'email' => $faculty->email,
            'department' => is_string($faculty->department) ? $faculty->department : ($faculty->departmentBelongsTo?->name ?? 'Unassigned'),
            'total_classes' => count($classes),
            'total_students_taught' => $totalStudents,
            'assigned_classes' => $classes,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'faculty_id' => $schema->integer()->description('Faculty ID number'),
            'faculty_name' => $schema->string()->description('Faculty name or partial name to search for'),
        ];
    }
}
