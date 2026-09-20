<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GetClassGradesTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieve student grades (prelim, midterm, finals, and remarks) and grade distributions for a specific class section or subject.';
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
            'class_enrollments.student',
            'faculty',
        ]);

        if (filled($validated['class_id'] ?? null)) {
            $class = $query->find($validated['class_id']);
        } elseif (filled($validated['subject_code'] ?? null)) {
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

            $class = $classesQuery->orderBy('section')->orderByDesc('id')->first();
        } else {
            return 'Please specify either a class_id or a subject_code to retrieve grades.';
        }

        if (! $class instanceof Classes) {
            $target = $validated['class_id'] ?? ($validated['subject_code'].(filled($validated['section'] ?? null) ? " ({$validated['section']})" : ''));

            return "Class '{$target}' was not found in records.";
        }

        $grades = [];
        $passedCount = 0;
        $failedCount = 0;
        $gradedCount = 0;

        foreach ($class->class_enrollments as $enrollment) {
            $student = $enrollment->student;
            $name = $student ? mb_trim("{$student->first_name} {$student->last_name}") : 'Unknown';

            $prelim = $enrollment->prelim_grade ? (float) $enrollment->prelim_grade : null;
            $midterm = $enrollment->midterm_grade ? (float) $enrollment->midterm_grade : null;
            $finals = $enrollment->finals_grade ? (float) $enrollment->finals_grade : null;

            $hasGrades = $prelim !== null || $midterm !== null || $finals !== null;
            if ($hasGrades) {
                $gradedCount++;
            }

            $remarks = $enrollment->remarks ?: 'Ongoing';
            if (mb_stripos((string) $remarks, 'pass') !== false || ($finals !== null && $finals <= 3.0 && $finals >= 1.0)) {
                $passedCount++;
            } elseif (mb_stripos((string) $remarks, 'fail') !== false || ($finals !== null && $finals > 3.0)) {
                $failedCount++;
            }

            $grades[] = [
                'student_id' => $student?->student_id,
                'name' => $name,
                'prelim' => $prelim,
                'midterm' => $midterm,
                'finals' => $finals,
                'remarks' => $remarks,
            ];
        }

        $totalEnrolled = count($grades);
        $passRate = $totalEnrolled > 0 ? round(($passedCount / $totalEnrolled) * 100, 1) : 0;

        return json_encode([
            'class_id' => $class->id,
            'subject_code' => $class->subject_code,
            'section' => $class->section,
            'faculty' => $class->faculty?->name ?? 'Unassigned',
            'school_year' => $class->school_year,
            'semester' => $class->semester,
            'total_students' => $totalEnrolled,
            'graded_students' => $gradedCount,
            'passed_count' => $passedCount,
            'failed_count' => $failedCount,
            'passing_rate_percent' => $passRate,
            'grade_sheet' => $grades,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_id' => $schema->integer()->description('Class ID number'),
            'subject_code' => $schema->string()->description('Subject code to inspect grades for (e.g. CS101)'),
            'section' => $schema->string()->description('Optional section filter (e.g. 1A)'),
            'school_year' => $schema->string()->description('Optional school year (e.g. 2026-2027)'),
            'semester' => $schema->integer()->description('Optional semester (1, 2, or summer)'),
        ];
    }
}
