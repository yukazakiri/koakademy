<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Classes;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class LookupClassSchedulesTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Search and list active classes and schedules across departments, subjects, sections, and faculty.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'query' => 'nullable|string',
            'subject_code' => 'nullable|string',
            'school_year' => 'nullable|string',
            'semester' => 'nullable|integer',
            'limit' => 'nullable|integer',
        ]);

        $limit = min(50, max(1, $validated['limit'] ?? 15));

        $classesQuery = Classes::query()
            ->with(['faculty', 'room', 'schedules', 'class_enrollments']);

        if (filled($validated['subject_code'] ?? null)) {
            $code = mb_trim((string) $validated['subject_code']);
            $codeClean = mb_strtolower(str_replace(['-', ' '], '', $code));

            $classesQuery->where(function ($q) use ($code, $codeClean) {
                $q->whereRaw("LOWER(REPLACE(REPLACE(subject_code, '-', ''), ' ', '')) = ?", [$codeClean])
                    ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ["%{$code}%"]);
            });
        }

        if (filled($validated['query'] ?? null)) {
            $searchTerm = mb_trim((string) $validated['query']);
            $param = "%{$searchTerm}%";

            $classesQuery->where(function ($q) use ($param) {
                $q->whereRaw('LOWER(subject_code) LIKE LOWER(?)', [$param])
                    ->orWhereRaw('LOWER(section) LIKE LOWER(?)', [$param])
                    ->orWhereHas('faculty', function ($fq) use ($param) {
                        $fq->whereRaw('LOWER(first_name) LIKE LOWER(?)', [$param])
                            ->orWhereRaw('LOWER(last_name) LIKE LOWER(?)', [$param])
                            ->orWhereRaw('LOWER(email) LIKE LOWER(?)', [$param])
                            ->orWhereRaw("LOWER(first_name || ' ' || last_name) LIKE LOWER(?)", [$param]);
                    });
            });
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
        }

        $classes = $classesQuery
            ->orderByDesc('school_year')
            ->orderBy('semester')
            ->orderBy('section')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function ($class) {
                $scheduleText = $class->schedules->map(function ($s) {
                    return "{$s->day_of_week} {$s->formatted_start_time}-{$s->formatted_end_time}";
                })->implode(', ');

                return [
                    'class_id' => $class->id,
                    'subject_code' => $class->subject_code,
                    'section' => $class->section,
                    'faculty' => $class->faculty?->name ?? 'TBA',
                    'room' => $class->room?->name ?? 'TBA',
                    'schedule' => $scheduleText ?: 'TBA',
                    'school_year' => $class->school_year,
                    'semester' => $class->semester,
                    'enrolled' => $class->class_enrollments->count(),
                    'slots' => $class->maximum_slots,
                ];
            })->values()->all();

        return json_encode([
            'count' => count($classes),
            'classes' => $classes,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Search term for subject code, section name, or faculty instructor'),
            'subject_code' => $schema->string()->description('Filter specifically by subject code'),
            'school_year' => $schema->string()->description('Optional school year (e.g. 2026-2027)'),
            'semester' => $schema->integer()->description('Optional semester (1, 2, or summer)'),
            'limit' => $schema->integer()->description('Max results to return (default 15, max 50)'),
        ];
    }
}
