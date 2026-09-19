<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GetClassEnrollmentsTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieve the complete list of students enrolled in a specific class section or subject. Can lookup by class_id or by subject_code and section.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'class_id' => 'nullable|integer',
            'subject_code' => 'nullable|string',
            'section' => 'nullable|string',
        ]);

        $query = Classes::query()->with([
            'class_enrollments.student.personalInfo',
            'faculty',
            'room',
            'schedules',
        ]);

        if (filled($validated['class_id'] ?? null)) {
            $class = $query->find($validated['class_id']);
        } elseif (filled($validated['subject_code'] ?? null)) {
            $q = $query->where('subject_code', $validated['subject_code']);
            if (filled($validated['section'] ?? null)) {
                $q->where('section', $validated['section']);
            }
            $class = $q->first();
        } else {
            return 'Please provide either a class_id or a subject_code to query enrolled students.';
        }

        if (! $class instanceof Classes) {
            $identifier = $validated['class_id'] ?? ($validated['subject_code'].($validated['section'] ? " (Section {$validated['section']})" : ''));

            return "Class '{$identifier}' was not found in records.";
        }

        $enrollments = $class->class_enrollments->map(function ($enrollment) {
            $student = $enrollment->student;
            $fullName = $student ? mb_trim("{$student->first_name} {$student->last_name}") : 'Unknown';

            return [
                'student_id' => $student?->id,
                'student_number' => $student?->student_id ?? $student?->id_number ?? 'N/A',
                'name' => $fullName ?: ($student?->name ?? 'Unknown'),
                'gender' => $student?->gender ?? $student?->personalInfo?->gender ?? 'N/A',
                'year_level' => $student?->academic_year ?? $student?->year_level ?? 'N/A',
                'status' => $enrollment->status ?? $student?->status ?? 'enrolled',
                'prelim_grade' => $enrollment->prelim_grade,
                'midterm_grade' => $enrollment->midterm_grade,
                'finals_grade' => $enrollment->finals_grade,
                'remarks' => $enrollment->remarks,
            ];
        })->values()->all();

        $scheduleText = $class->schedules->map(function ($s) {
            return "{$s->day_of_week} {$s->formatted_start_time} - {$s->formatted_end_time}";
        })->implode(', ');

        return json_encode([
            'class_id' => $class->id,
            'subject_code' => $class->subject_code,
            'section' => $class->section,
            'faculty' => $class->faculty?->name ?? 'Unassigned',
            'room' => $class->room?->name ?? 'TBA',
            'schedule' => $scheduleText ?: 'TBA',
            'school_year' => $class->school_year,
            'semester' => $class->semester,
            'enrolled_count' => count($enrollments),
            'maximum_slots' => $class->maximum_slots,
            'students' => $enrollments,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_id' => $schema->integer()->description('The numeric ID of the class'),
            'subject_code' => $schema->string()->description('The subject code (e.g. CS101, ENG1, MATH10)'),
            'section' => $schema->string()->description('Optional class section (e.g. 1A, BSIT-1B)'),
        ];
    }
}
