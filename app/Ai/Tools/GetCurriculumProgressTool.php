<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use App\Models\User;
use App\Services\ApiIdentityService;
use App\Services\StudentChecklistService;
use App\Services\TenantContext;
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
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required to view curriculum progress.']);
        }

        if (! $user->hasRole('super_admin') && ! $user->hasRole('developer') && ! $user->isAdministrative() && ! $user->can('View:Student') && ! $user->isStudentRole()) {
            return json_encode(['error' => true, 'message' => 'You are not permitted to view curriculum progress.']);
        }

        $studentId = $request['student_id'] ?? null;
        if (! $studentId && ! $user->isStudentRole()) {
            return json_encode(['error' => true, 'message' => 'student_id is required.']);
        }

        if ($user->isStudentRole()) {
            $ownStudent = app(ApiIdentityService::class)->studentFor($user);
            if (! $ownStudent instanceof Student) {
                return json_encode(['error' => true, 'message' => 'No student profile is associated with your account.']);
            }
            if ($studentId !== null && (string) $ownStudent->id !== (string) $studentId && (string) $ownStudent->student_id !== (string) $studentId) {
                return json_encode(['error' => true, 'message' => 'Students can only access their own curriculum progress.']);
            }
            $student = $ownStudent;
        } else {
            $student = $this->resolveStudent((string) $studentId);
        }

        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => "Student '{$studentId}' not found."]);
        }

        $options = [
            'year_level' => isset($request['year_level']) ? (int) $request['year_level'] : null,
            'semester' => isset($request['semester']) ? (int) $request['semester'] : null,
            'status' => isset($request['status']) ? (string) $request['status'] : null,
        ];

        $analysis = $this->checklistService->analyze($student, $options);
        $summary = is_array($analysis['academic_summary'] ?? null) ? $analysis['academic_summary'] : null;
        $totalCurriculumSubjects = (int) ($summary['total_curriculum_subjects'] ?? 0);

        if (($analysis['error'] ?? false) === true || $summary === null || $totalCurriculumSubjects === 0) {
            $reason = ($analysis['error'] ?? false) === true
                ? 'no_program_assigned'
                : 'curriculum_subjects_not_configured';
            $message = ($analysis['error'] ?? false) === true
                ? (string) ($analysis['message'] ?? 'Student has no degree program assigned.')
                : 'No curriculum subjects are configured for the student\'s assigned degree program.';

            return json_encode([
                'status' => 'unavailable',
                'available' => false,
                'reason' => $reason,
                'message' => $message,
                'student_id' => $student->id,
                'student_name' => $student->full_name,
                'program_track' => $student->Course?->title,
                'progress_percentage' => null,
                'completed_units' => null,
                'remaining_units' => null,
                'total_units_required' => null,
                'cumulative_gwa' => null,
                'deficiencies' => [],
                'academic_summary' => $summary,
                'checklist' => [],
                'failed_retakes_needed' => [],
                'prerequisite_blockers' => [],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $totalRequiredUnits = (int) ($summary['total_curriculum_units'] ?? 0);
        $completedUnits = (int) ($summary['completed_units'] ?? 0);
        $remainingUnits = (int) ($summary['remaining_units'] ?? max(0, $totalRequiredUnits - $completedUnits));
        $progressPercent = (float) ($summary['progress_percentage'] ?? 0.0);

        $payload = [
            'status' => 'analyzed',
            'available' => true,
            'student_id' => $student->id,
            'student_name' => $student->full_name,
            'program_track' => $student->Course?->title,
            'progress_percentage' => $progressPercent,
            'completed_units' => $completedUnits,
            'remaining_units' => $remainingUnits,
            'total_units_required' => $totalRequiredUnits,
            'cumulative_gwa' => $summary['cumulative_gwa'] ?? null,
            'deficiencies' => ! empty($analysis['deficiencies'])
                ? array_values(array_column($analysis['deficiencies'], 'title'))
                : [],
            'academic_summary' => $summary,
            'checklist' => $analysis['checklist'] ?? [],
            'failed_retakes_needed' => $analysis['failed_retakes_needed'] ?? [],
            'prerequisite_blockers' => $analysis['prerequisite_blockers'] ?? [],
        ];

        // If summary mode requested, return academic summary only to save tokens
        if ($request['summary_only'] ?? false) {
            return json_encode([
                'status' => 'analyzed',
                'available' => true,
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
        $schoolId = app(TenantContext::class)->getCurrentSchoolId();
        $query = Student::query();
        if ($schoolId !== null) {
            $query->where('school_id', $schoolId);
        }

        if (is_numeric($identifier)) {
            $found = (clone $query)->find((int) $identifier);
            if ($found instanceof Student) {
                return $found;
            }
        }

        return $query
            ->where(function ($q) use ($identifier): void {
                $q->where('student_id', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();
    }
}
