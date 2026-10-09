<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Course;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\StudentDataTransferService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

final class ManageEnrollmentTool implements Tool
{
    use InteractsWithApprovals;

    public function __construct(
        private ?StudentDataTransferService $transferService = null,
        private ?GeneralSettingsService $settingsService = null,
    ) {
        $this->transferService ??= app(StudentDataTransferService::class);
        $this->settingsService ??= app(GeneralSettingsService::class);
    }

    public function description(): Stringable|string
    {
        return 'Create, update, cancel, soft-delete, restore, transfer between students, or inspect student enrollment records. Modifications require administrator approval.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required.']);
        }

        $action = mb_strtolower((string) $request['action']);

        if ($action === 'get') {
            if (! $user->hasRole('super_admin') && ! $user->can('View:StudentEnrollment')) {
                return json_encode(['error' => true, 'message' => 'You are not permitted to view enrollment records.']);
            }

            return $this->handleGet($request);
        }

        if (! $user->hasRole('super_admin') && ! $user->can('Update:StudentEnrollment')) {
            return json_encode(['error' => true, 'message' => 'You are not permitted to manage enrollment records.']);
        }

        return match ($action) {
            'create' => $this->handleCreate($request, $user),
            'update' => $this->handleUpdate($request, $user),
            'update_status' => $this->handleUpdateStatus($request, $user),
            'soft_delete', 'delete' => $this->handleSoftDelete($request, $user),
            'restore' => $this->handleRestore($request, $user),
            'transfer_to_student' => $this->handleTransferToStudent($request, $user),
            default => json_encode(['error' => true, 'message' => "Unknown action '{$action}'. Supported: create, update, update_status, soft_delete, restore, transfer_to_student, get."]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create', 'update', 'update_status', 'soft_delete', 'restore', 'transfer_to_student', 'get'])
                ->required()
                ->description('Enrollment lifecycle operation to perform.'),
            'enrollment_id' => $schema->integer()
                ->description('Internal StudentEnrollment ID for update, status, delete, restore, transfer, or get.'),
            'student_id' => $schema->string()
                ->description('Student database ID or student number for create or get.'),
            'target_student_id' => $schema->string()
                ->description('Target student database ID or student number for transfer_to_student.'),
            'course_code' => $schema->string()
                ->description('Academic program code (e.g. BSIT, BSCS) for create or update.'),
            'course_id' => $schema->integer()
                ->description('Program database ID.'),
            'school_year' => $schema->string()
                ->description('School year string (e.g. "2025 - 2026"). Defaults to current active term.'),
            'semester' => $schema->integer()
                ->enum([1, 2])
                ->description('Semester: 1 or 2.'),
            'academic_year' => $schema->integer()
                ->description('Student year level (1 to 5).'),
            'status' => $schema->string()
                ->enum(['enrolled', 'pending', 'cancelled', 'dropped', 'completed', 'transferred'])
                ->description('Enrollment status for create or update_status.'),
            'downpayment' => $schema->number()
                ->description('Assessed or received downpayment amount.'),
            'remarks' => $schema->string()
                ->description('Administrative remarks or explanatory notes.'),
            'reason' => $schema->string()
                ->description('Reason for status change, cancellation, soft delete, or transfer.'),
            'include_trashed' => $schema->boolean()
                ->description('When true for get, includes soft-deleted enrollments.'),
            'preview' => $schema->boolean()
                ->description('When true for transfer_to_student, previews the transfer impact without committing.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        if (session('ai_auto_approve_actions', false) || request()->boolean('auto_approve', false)) {
            return false;
        }

        $action = mb_strtolower((string) ($request['action'] ?? ''));

        if ($action === 'get') {
            return false;
        }

        if ($action === 'create') {
            $student = $request['student_id'] ?? 'student';
            $term = ($request['school_year'] ?? 'current year').' Sem '.($request['semester'] ?? 'current sem');

            return Approval::required("Create new official enrollment record for {$student} for {$term}?");
        }

        if ($action === 'update') {
            $id = $request['enrollment_id'] ?? 'unknown';

            return Approval::required("Update enrollment record #{$id}?");
        }

        if ($action === 'update_status') {
            $id = $request['enrollment_id'] ?? 'unknown';
            $status = $request['status'] ?? 'unknown';
            $reason = ! empty($request['reason']) ? " Reason: {$request['reason']}" : '';

            return Approval::required("Change enrollment #{$id} status to '{$status}'?{$reason}");
        }

        if ($action === 'soft_delete' || $action === 'delete') {
            $id = $request['enrollment_id'] ?? 'unknown';
            $reason = ! empty($request['reason']) ? " Reason: {$request['reason']}" : '';

            return Approval::required("Soft-delete enrollment record #{$id}?{$reason} This will move it to trash.");
        }

        if ($action === 'restore') {
            $id = $request['enrollment_id'] ?? 'unknown';

            return Approval::required("Restore soft-deleted enrollment record #{$id}?");
        }

        if ($action === 'transfer_to_student') {
            if (request()->boolean('preview', false) || ($request['preview'] ?? false)) {
                return false;
            }

            $id = $request['enrollment_id'] ?? 'unknown';
            $target = $request['target_student_id'] ?? 'target student';

            return Approval::required("Transfer enrollment #{$id} to student {$target}? This will permanently reassign the enrollment and associated records.");
        }

        return false;
    }

    private function handleGet(Request $request): string
    {
        $enrollmentId = $request['enrollment_id'] ? (int) $request['enrollment_id'] : null;
        $studentId = $request['student_id'] ?? null;
        $includeTrashed = (bool) ($request['include_trashed'] ?? false);

        $query = $includeTrashed ? StudentEnrollment::withTrashed() : StudentEnrollment::query();

        if ($enrollmentId !== null) {
            $enrollment = (clone $query)->with(['student', 'course', 'subjectsEnrolled.subject'])->find($enrollmentId);
            if (! $enrollment instanceof StudentEnrollment) {
                return json_encode(['found' => false, 'message' => "Enrollment ID #{$enrollmentId} not found."]);
            }

            return json_encode([
                'found' => true,
                'enrollment' => $this->formatEnrollment($enrollment),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (filled($studentId)) {
            $student = $this->resolveStudent((string) $studentId, withTrashed: $includeTrashed);
            if (! $student instanceof Student) {
                return json_encode(['found' => false, 'message' => "Student '{$studentId}' not found."]);
            }

            $enrollments = (clone $query)
                ->where('student_id', (string) $student->id)
                ->with(['course', 'subjectsEnrolled.subject'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn (StudentEnrollment $e) => $this->formatEnrollment($e))
                ->all();

            return json_encode([
                'found' => true,
                'student' => [
                    'id' => $student->id,
                    'student_number' => (string) $student->student_id,
                    'name' => $student->full_name,
                ],
                'count' => count($enrollments),
                'enrollments' => $enrollments,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return json_encode(['error' => true, 'message' => 'Either enrollment_id or student_id is required.']);
    }

    private function handleCreate(Request $request, User $actor): string
    {
        $studentId = $request['student_id'] ?? null;
        if (! $studentId) {
            return json_encode(['error' => true, 'message' => 'student_id is required to create an enrollment.']);
        }

        $student = $this->resolveStudent((string) $studentId);
        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => "Student '{$studentId}' not found."]);
        }

        $schoolYear = (string) ($request['school_year'] ?: $this->settingsService->getCurrentSchoolYearString());
        $semester = (int) ($request['semester'] ?: $this->settingsService->getCurrentSemester());

        $courseId = null;
        if (! empty($request['course_id'])) {
            $courseId = (int) $request['course_id'];
        } elseif (! empty($request['course_code'])) {
            $code = mb_strtoupper(mb_trim((string) $request['course_code']));
            $course = Course::query()->where('code', $code)->first();
            $courseId = $course?->id;
        } else {
            $courseId = $student->course_id;
        }

        $status = (string) ($request['status'] ?: 'enrolled');
        $academicYear = (int) ($request['academic_year'] ?: ($student->academic_year ?: 1));
        $downpayment = (float) ($request['downpayment'] ?: 0.0);
        $remarks = (string) ($request['remarks'] ?? 'Created via AI ManageEnrollmentTool.');

        $schoolId = $student->school_id ?: 1;

        $enrollment = DB::transaction(function () use ($student, $schoolId, $courseId, $schoolYear, $semester, $academicYear, $status, $downpayment, $remarks, $actor): StudentEnrollment {
            $created = StudentEnrollment::query()->create([
                'student_id' => (string) $student->id,
                'school_id' => $schoolId,
                'course_id' => $courseId,
                'school_year' => $schoolYear,
                'semester' => $semester,
                'academic_year' => $academicYear,
                'status' => $status,
                'downpayment' => $downpayment,
                'remarks' => $remarks,
                'current_step_key' => 'registration',
                'workflow_runtime' => StudentEnrollment::WorkflowRuntimePolicyV1,
            ]);

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $created->id,
                'actor_id' => $actor->id,
                'event_type' => 'enrollment_created',
                'reason' => 'Official enrollment created via AI copilot.',
                'from_step_key' => null,
                'to_step_key' => 'registration',
                'status' => $status,
            ]);

            return $created;
        });

        return json_encode([
            'success' => true,
            'action' => 'create',
            'message' => "Successfully created enrollment #{$enrollment->id} for {$student->full_name}.",
            'enrollment' => $this->formatEnrollment($enrollment->fresh(['course', 'student'])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleUpdate(Request $request, User $actor): string
    {
        $enrollmentId = (int) ($request['enrollment_id'] ?? 0);
        $enrollment = StudentEnrollment::query()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return json_encode(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
        }

        $fields = [];
        if (! empty($request['course_code'])) {
            $code = mb_strtoupper(mb_trim((string) $request['course_code']));
            $course = Course::query()->where('code', $code)->first();
            if ($course) {
                $fields['course_id'] = $course->id;
            }
        } elseif (! empty($request['course_id'])) {
            $fields['course_id'] = (int) $request['course_id'];
        }

        if (isset($request['school_year'])) {
            $fields['school_year'] = (string) $request['school_year'];
        }
        if (isset($request['semester'])) {
            $fields['semester'] = (int) $request['semester'];
        }
        if (isset($request['academic_year'])) {
            $fields['academic_year'] = (int) $request['academic_year'];
        }
        if (isset($request['downpayment'])) {
            $fields['downpayment'] = (float) $request['downpayment'];
        }
        if (isset($request['remarks'])) {
            $fields['remarks'] = (string) $request['remarks'];
        }

        if ($fields === []) {
            return json_encode(['error' => true, 'message' => 'No updatable fields provided.']);
        }

        $enrollment->update($fields);

        EnrollmentWorkflowEvent::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'actor_id' => $actor->id,
            'event_type' => 'enrollment_updated',
            'reason' => (string) ($request['reason'] ?? 'Enrollment details updated via AI copilot.'),
            'from_step_key' => $enrollment->current_step_key,
            'to_step_key' => $enrollment->current_step_key,
            'status' => $enrollment->status,
        ]);

        return json_encode([
            'success' => true,
            'action' => 'update',
            'message' => "Enrollment #{$enrollment->id} updated successfully.",
            'enrollment' => $this->formatEnrollment($enrollment->fresh(['course', 'student'])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleUpdateStatus(Request $request, User $actor): string
    {
        $enrollmentId = (int) ($request['enrollment_id'] ?? 0);
        $newStatus = mb_strtolower((string) ($request['status'] ?? ''));
        $reason = (string) ($request['reason'] ?? 'Status updated via AI copilot.');

        $enrollment = StudentEnrollment::query()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return json_encode(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
        }

        $oldStatus = $enrollment->status;
        $enrollment->update(['status' => $newStatus]);

        EnrollmentWorkflowEvent::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'actor_id' => $actor->id,
            'event_type' => 'status_changed',
            'reason' => "Status changed from '{$oldStatus}' to '{$newStatus}': {$reason}",
            'from_step_key' => $enrollment->current_step_key,
            'to_step_key' => $enrollment->current_step_key,
            'status' => $newStatus,
        ]);

        return json_encode([
            'success' => true,
            'action' => 'update_status',
            'message' => "Enrollment #{$enrollment->id} status updated from '{$oldStatus}' to '{$newStatus}'.",
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'enrollment_id' => $enrollment->id,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleSoftDelete(Request $request, User $actor): string
    {
        $enrollmentId = (int) ($request['enrollment_id'] ?? 0);
        $reason = (string) ($request['reason'] ?? 'Soft-deleted via AI copilot.');

        $enrollment = StudentEnrollment::query()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return json_encode(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
        }

        EnrollmentWorkflowEvent::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'actor_id' => $actor->id,
            'event_type' => 'enrollment_soft_deleted',
            'reason' => $reason,
            'from_step_key' => $enrollment->current_step_key,
            'to_step_key' => $enrollment->current_step_key,
            'status' => $enrollment->status,
        ]);

        $enrollment->delete();

        return json_encode([
            'success' => true,
            'action' => 'soft_delete',
            'message' => "Enrollment #{$enrollmentId} soft-deleted successfully.",
            'enrollment_id' => $enrollmentId,
            'reason' => $reason,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleRestore(Request $request, User $actor): string
    {
        $enrollmentId = (int) ($request['enrollment_id'] ?? 0);
        $enrollment = StudentEnrollment::withTrashed()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return json_encode(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found (including trashed)."]);
        }

        if (! $enrollment->trashed()) {
            return json_encode([
                'success' => true,
                'action' => 'restore',
                'message' => "Enrollment #{$enrollmentId} is already active.",
                'already_active' => true,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $enrollment->restore();

        EnrollmentWorkflowEvent::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'actor_id' => $actor->id,
            'event_type' => 'enrollment_restored',
            'reason' => (string) ($request['reason'] ?? 'Restored from trash via AI copilot.'),
            'from_step_key' => $enrollment->current_step_key,
            'to_step_key' => $enrollment->current_step_key,
            'status' => $enrollment->status,
        ]);

        return json_encode([
            'success' => true,
            'action' => 'restore',
            'message' => "Enrollment #{$enrollmentId} restored successfully.",
            'enrollment' => $this->formatEnrollment($enrollment->fresh(['course', 'student'])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleTransferToStudent(Request $request, User $actor): string
    {
        $enrollmentId = (int) ($request['enrollment_id'] ?? 0);
        $targetId = (string) ($request['target_student_id'] ?? '');

        if (blank($targetId)) {
            return json_encode(['error' => true, 'message' => 'target_student_id is required.']);
        }

        $enrollment = StudentEnrollment::withTrashed()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return json_encode(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
        }

        $sourceStudent = $enrollment->student;
        if (! $sourceStudent instanceof Student) {
            return json_encode(['error' => true, 'message' => 'Source student record could not be resolved.']);
        }

        $targetStudent = $this->resolveStudent($targetId, withTrashed: true);
        if (! $targetStudent instanceof Student) {
            return json_encode(['error' => true, 'message' => "Target student '{$targetId}' not found."]);
        }

        $options = [
            'enrollment_id' => $enrollment->id,
            'reason' => (string) ($request['reason'] ?? "Transfer enrollment #{$enrollment->id} to {$targetStudent->full_name}."),
        ];

        try {
            if ($request['preview'] ?? false) {
                $previewData = $this->transferService->preview($sourceStudent, $targetStudent, $options);

                return json_encode([
                    'success' => true,
                    'action' => 'transfer_enrollment_preview',
                    ...$previewData,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }

            $result = $this->transferService->transfer($sourceStudent, $targetStudent, $actor, $options);

            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()]);
        }
    }

    /** @return array<string, mixed> */
    private function formatEnrollment(StudentEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'student_id' => $enrollment->student_id,
            'student_name' => $enrollment->student?->full_name ?? 'N/A',
            'course' => $enrollment->course?->code ?? 'N/A',
            'school_year' => $enrollment->school_year,
            'semester' => $enrollment->semester,
            'academic_year' => $enrollment->academic_year,
            'status' => $enrollment->status,
            'current_step_key' => $enrollment->current_step_key,
            'downpayment' => (float) $enrollment->downpayment,
            'remarks' => $enrollment->remarks,
            'is_trashed' => $enrollment->trashed(),
            'deleted_at' => $enrollment->deleted_at?->toIso8601String(),
            'enrolled_subjects_count' => $enrollment->subjectsEnrolled->count(),
        ];
    }

    private function resolveStudent(string $identifier, bool $withTrashed = false): ?Student
    {
        $query = $withTrashed ? Student::withTrashed() : Student::query();

        if (is_numeric($identifier)) {
            $found = (clone $query)->find((int) $identifier);
            if ($found) {
                return $found;
            }
        }

        return $query->where('student_id', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
}
