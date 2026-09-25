<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceSession;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GetClassAttendanceSummaryTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Retrieve attendance metrics, total session count, and student attendance breakdowns (present, late, absent, excused) for a class section.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required.'], JSON_PRETTY_PRINT);
        }

        $faculty = Faculty::query()->where('user_id', $user->id)->first();
        if (! $faculty instanceof Faculty) {
            return json_encode(['error' => true, 'message' => 'Faculty record not found for this account.'], JSON_PRETTY_PRINT);
        }

        $validated = $request->validate([
            'class_id' => 'nullable|integer',
            'subject_code' => 'nullable|string',
            'section' => 'nullable|string',
            'school_year' => 'nullable|string',
            'semester' => 'nullable|integer',
        ]);

        $query = Classes::query()
            ->where('faculty_id', $faculty->id)
            ->with([
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
            return 'Please specify either a class_id or a subject_code to query attendance.';
        }

        if (! $class instanceof Classes) {
            $target = $validated['class_id'] ?? ($validated['subject_code'].(filled($validated['section'] ?? null) ? " ({$validated['section']})" : ''));

            return "Class '{$target}' was not found in records or does not belong to your teaching assignments.";
        }

        $sessionsCount = ClassAttendanceSession::query()
            ->where('class_id', $class->id)
            ->where('is_no_meeting', false)
            ->count();

        $records = ClassAttendanceRecord::query()
            ->where('class_id', $class->id)
            ->get();

        $studentAttendance = [];

        foreach ($class->class_enrollments as $enrollment) {
            $student = $enrollment->student;
            $name = $student ? mb_trim("{$student->first_name} {$student->last_name}") : 'Unknown';

            $studentRecords = $records->where('student_id', $student?->id);
            $present = $studentRecords->where('status', 'present')->count();
            $late = $studentRecords->where('status', 'late')->count();
            $absent = $studentRecords->where('status', 'absent')->count();
            $excused = $studentRecords->where('status', 'excused')->count();

            $effectiveSessions = max($sessionsCount, $present + $late + $absent + $excused);
            $attended = $present + ($late * 0.5) + $excused;
            $rate = $effectiveSessions > 0 ? round(($attended / $effectiveSessions) * 100, 1) : 100.0;

            $studentAttendance[] = [
                'student_id' => $student?->student_id,
                'name' => $name,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'excused' => $excused,
                'attendance_rate_percent' => $rate,
            ];
        }

        $overallRate = count($studentAttendance) > 0
            ? round(array_sum(array_column($studentAttendance, 'attendance_rate_percent')) / count($studentAttendance), 1)
            : 100.0;

        return json_encode([
            'class_id' => $class->id,
            'subject_code' => $class->subject_code,
            'section' => $class->section,
            'faculty' => $class->faculty?->name ?? 'Unassigned',
            'school_year' => $class->school_year,
            'semester' => $class->semester,
            'total_sessions_conducted' => $sessionsCount,
            'overall_attendance_rate_percent' => $overallRate,
            'students' => $studentAttendance,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_id' => $schema->integer()->description('Class ID number'),
            'subject_code' => $schema->string()->description('Subject code (e.g. CS101, GE-1)'),
            'section' => $schema->string()->description('Optional class section (e.g. 1A, A, B)'),
            'school_year' => $schema->string()->description('Optional school year (e.g. 2026-2027)'),
            'semester' => $schema->integer()->description('Optional semester (1, 2, or summer)'),
        ];
    }
}
