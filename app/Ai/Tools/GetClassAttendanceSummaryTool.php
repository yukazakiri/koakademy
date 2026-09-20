<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceSession;
use App\Models\Classes;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
        $validated = $request->validate([
            'class_id' => 'nullable|integer',
            'subject_code' => 'nullable|string',
            'section' => 'nullable|string',
        ]);

        $query = Classes::query()->with([
            'class_enrollments.student',
            'faculty',
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
            return 'Please specify either a class_id or a subject_code to query attendance.';
        }

        if (! $class instanceof Classes) {
            $target = $validated['class_id'] ?? ($validated['subject_code'].($validated['section'] ? " ({$validated['section']})" : ''));

            return "Class '{$target}' was not found in records.";
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
            'total_sessions_conducted' => $sessionsCount,
            'overall_attendance_rate_percent' => $overallRate,
            'students' => $studentAttendance,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'class_id' => $schema->integer()->description('Class ID number'),
            'subject_code' => $schema->string()->description('Subject code (e.g. CS101)'),
            'section' => $schema->string()->description('Optional class section (e.g. 1A)'),
        ];
    }
}
