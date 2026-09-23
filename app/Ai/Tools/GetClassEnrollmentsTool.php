<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
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
            'school_year' => 'nullable|string',
            'semester' => 'nullable|integer',
        ]);

        $query = Classes::query()->with([
            'class_enrollments.student.personalInfo',
            'faculty',
            'room',
            'schedules',
        ]);

        if (filled($validated['class_id'] ?? null)) {
            $class = $query->find($validated['class_id']);

            if (! $class instanceof Classes) {
                return "Class #{$validated['class_id']} was not found in records.";
            }

            return $this->formatSingleClassResponse($class);
        }

        if (! filled($validated['subject_code'] ?? null)) {
            return 'Please provide either a class_id or a subject_code to query enrolled students.';
        }

        $code = mb_trim((string) $validated['subject_code']);
        $codeClean = mb_strtolower(str_replace(['-', ' '], '', $code));

        $classesQuery = $query->where(function ($q) use ($code, $codeClean) {
            $q->whereRaw("LOWER(REPLACE(REPLACE(subject_code, '-', ''), ' ', '')) = ?", [$codeClean])
                ->orWhereRaw('LOWER(subject_code) = LOWER(?)', [$code]);
        });

        if (filled($validated['section'] ?? null)) {
            $section = mb_trim((string) $validated['section']);
            $classesQuery->whereRaw('LOWER(section) = LOWER(?)', [$section]);
        }

        if (filled($validated['school_year'] ?? null) && filled($validated['semester'] ?? null)) {
            $classesQuery->forAcademicPeriod((string) $validated['school_year'], (int) $validated['semester']);
        } elseif (filled($validated['school_year'] ?? null)) {
            $sy = (string) $validated['school_year'];
            $normalized = GeneralSettingsService::normalizeSchoolYear($sy);
            $compact = str_replace(' ', '', $normalized);
            $classesQuery->whereIn('school_year', array_unique([$normalized, $compact]));
        } elseif (filled($validated['semester'] ?? null)) {
            $classesQuery->where('semester', (int) $validated['semester']);
        } else {
            $currentPeriodQuery = (clone $classesQuery)->currentAcademicPeriod();
            if ($currentPeriodQuery->exists()) {
                $classesQuery = $currentPeriodQuery;
            } else {
                $latest = (clone $classesQuery)->orderByDesc('school_year')->orderByDesc('semester')->orderByDesc('id')->first();
                if ($latest) {
                    $classesQuery->forAcademicPeriod((string) $latest->school_year, (int) $latest->semester);
                }
            }
        }

        $classes = $classesQuery->orderBy('section')->orderByDesc('id')->get();

        if ($classes->isEmpty()) {
            $identifier = $validated['subject_code'].(filled($validated['section'] ?? null) ? " (Section {$validated['section']})" : '');

            return "Class '{$identifier}' was not found in records.";
        }

        if ($classes->count() === 1) {
            return $this->formatSingleClassResponse($classes->first());
        }

        return $this->formatMultiClassResponse($classes);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_id' => $schema->integer()->description('The numeric ID of the class'),
            'subject_code' => $schema->string()->description('The subject code (e.g. CS101, ENG1, MATH10, GE-1)'),
            'section' => $schema->string()->description('Optional class section (e.g. A, B, 1A, BSIT-1B)'),
            'school_year' => $schema->string()->description('Optional school year (e.g. 2026-2027)'),
            'semester' => $schema->integer()->description('Optional semester (1, 2, or summer)'),
        ];
    }

    /**
     * @param  Collection<int, Classes>  $classes
     */
    private function formatMultiClassResponse(Collection $classes): string
    {
        $allStudents = [];
        $sectionsSummary = [];

        foreach ($classes as $class) {
            $scheduleText = $class->schedules->map(function ($s) {
                return "{$s->day_of_week} {$s->formatted_start_time} - {$s->formatted_end_time}";
            })->implode(', ');

            $enrollments = $this->mapEnrollments($class);

            $sectionsSummary[] = [
                'class_id' => $class->id,
                'section' => $class->section,
                'faculty' => $class->faculty?->name ?? 'Unassigned',
                'room' => $class->room?->name ?? 'TBA',
                'schedule' => $scheduleText ?: 'TBA',
                'enrolled_count' => count($enrollments),
                'maximum_slots' => $class->maximum_slots,
            ];

            $allStudents = array_merge($allStudents, $enrollments);
        }

        $first = $classes->first();
        $sectionsList = $classes->pluck('section')->filter()->values()->all();

        return json_encode([
            'class_id' => null,
            'subject_code' => $first->subject_code,
            'section' => 'All Sections ('.implode(', ', $sectionsList).')',
            'school_year' => $first->school_year,
            'semester' => $first->semester,
            'total_sections' => count($classes),
            'enrolled_count' => count($allStudents),
            'sections' => $sectionsSummary,
            'students' => $allStudents,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function formatSingleClassResponse(Classes $class): string
    {
        $enrollments = $this->mapEnrollments($class);

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
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mapEnrollments(Classes $class): array
    {
        return $class->class_enrollments->map(function ($enrollment) use ($class) {
            $student = $enrollment->student;
            $fullName = $student ? mb_trim("{$student->first_name} {$student->last_name}") : 'Unknown';

            $item = [
                'student_number' => (string) ($student?->student_id ?? $student?->id_number ?? 'N/A'),
                'name' => $fullName ?: ($student?->name ?? 'Unknown'),
                'gender' => (string) ($student?->gender ?? $student?->personalInfo?->gender ?? 'N/A'),
                'year_level' => (string) ($student?->academic_year ?? $student?->year_level ?? 'N/A'),
                'section' => (string) $class->section,
                'status' => $enrollment->status ? 'Active' : 'Inactive',
            ];

            if ($enrollment->finals_grade !== null) {
                $item['grade'] = $enrollment->finals_grade;
            }

            return $item;
        })->values()->all();
    }
}
