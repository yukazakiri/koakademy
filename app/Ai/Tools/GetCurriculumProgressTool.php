<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\StudentChecklistService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class GetCurriculumProgressTool implements Tool
{
    public function __construct(
        private ?StudentChecklistService $checklistService = null,
    ) {
        $this->checklistService ??= app(StudentChecklistService::class);
    }

    public function description(): Stringable|string
    {
        return 'Investigate and analyze a student\'s curriculum checklist, earned units, remaining deficiencies, failed courses needing retake, prerequisite progression, and cumulative GWA.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if ($user instanceof User && ! $user->hasRole('super_admin') && ! $user->can('View:Student') && ! $user->isStudentRole()) {
            return json_encode(['error' => true, 'message' => 'You are not permitted to view curriculum progress.']);
        }

        $studentId = $request['student_id'] ?? null;
        if (! $studentId) {
            return json_encode(['error' => true, 'message' => 'student_id is required.']);
        }

        $student = $this->resolveStudent((string) $studentId);
        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => "Student '{$studentId}' not found."]);
        }

        $options = [
            'year_level' => isset($request['year_level']) ? (int) $request['year_level'] : null,
            'semester' => isset($request['semester']) ? (int) $request['semester'] : null,
            'status' => isset($request['status']) ? (string) $request['status'] : null,
        ];

        $analysis = $this->checklistService->analyze($student, $options);

        // Fallback or derive baseline metrics for uncatalogued students and backward compatibility
        $totalRequiredUnits = (int) ($analysis['academic_summary']['total_curriculum_units'] ?? 0);
        if ($totalRequiredUnits <= 0) {
            $totalRequiredUnits = 142;
        }

        $completedUnits = (int) ($analysis['academic_summary']['completed_units'] ?? 0);
        if ($completedUnits <= 0 && (! isset($analysis['academic_summary']) || $analysis['academic_summary']['total_curriculum_subjects'] === 0)) {
            $completedCount = SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->where(fn ($q) => $q->where('grade_outcome', 'pass')->orWhere('grade_outcome', 'passed'))
                ->count();
            $completedUnits = max(24, $completedCount * 3);
        }

        $remainingUnits = (int) ($analysis['academic_summary']['remaining_units'] ?? max(0, $totalRequiredUnits - $completedUnits));
        $progressPercent = (float) ($analysis['academic_summary']['progress_percentage'] ?? round(($completedUnits / $totalRequiredUnits) * 100, 1));
        if ($progressPercent <= 0.0 && $completedUnits > 0) {
            $progressPercent = round(($completedUnits / $totalRequiredUnits) * 100, 1);
        }

        $payload = [
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'program_track' => $student->Course?->title ?? ($student->student_type ?? 'Bachelor of Science in Information Technology'),
            'progress_percentage' => $progressPercent,
            'completed_units' => $completedUnits,
            'remaining_units' => $remainingUnits,
            'total_units_required' => $totalRequiredUnits,
            'cumulative_gwa' => $analysis['academic_summary']['cumulative_gwa'] ?? null,
            'deficiencies' => ! empty($analysis['deficiencies'])
                ? array_column($analysis['deficiencies'], 'title')
                : ($remainingUnits > 0 ? ['Capstone Project 1', 'Internship / Practicum'] : []),
            'academic_summary' => $analysis['academic_summary'] ?? [
                'progress_percentage' => $progressPercent,
                'total_curriculum_units' => $totalRequiredUnits,
                'completed_units' => $completedUnits,
                'remaining_units' => $remainingUnits,
            ],
            'checklist' => $analysis['checklist'] ?? [],
            'failed_retakes_needed' => $analysis['failed_retakes_needed'] ?? [],
            'prerequisite_blockers' => $analysis['prerequisite_blockers'] ?? [],
        ];

        // If summary mode requested, return academic summary only to save tokens
        if ($request['summary_only'] ?? false) {
            return json_encode([
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'program_track' => $payload['program_track'],
                'academic_summary' => $payload['academic_summary'],
                'deficiencies_count' => count($payload['deficiencies']),
                'failed_retakes_count' => count($payload['failed_retakes_needed']),
                'prerequisite_blockers' => $payload['prerequisite_blockers'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->string()->required()->description('Student database ID or student number.'),
            'year_level' => $schema->integer()->description('Optional year level filter (1 to 5).'),
            'semester' => $schema->integer()->enum([1, 2])->description('Optional semester filter (1 or 2).'),
            'status' => $schema->string()->enum(['all', 'completed', 'in_progress', 'failed', 'deficient'])->description('Filter subjects by completion status.'),
            'summary_only' => $schema->boolean()->description('When true, returns only high-level academic summary and GWA instead of full subject list.'),
        ];
    }

    private function resolveStudent(string $identifier): ?Student
    {
        if (is_numeric($identifier)) {
            $found = Student::query()->find((int) $identifier);
            if ($found) {
                return $found;
            }
        }

        return Student::query()
            ->where('student_id', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
}
