<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Course;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\GeneralSettingsService;
use App\Services\StudentDataTransferService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Throwable;

#[Description('Manage the complete student enrollment lifecycle: create new term enrollment, update details or status, soft-delete, restore, transfer enrollment to another student, or retrieve enrollment records including trashed. Requires MCP write key and appropriate registrar permissions.')]
#[IsIdempotent]
final class ManageEnrollmentTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?StudentDataTransferService $transferService = null,
        private ?GeneralSettingsService $settingsService = null,
    ) {
        $this->transferService ??= app(StudentDataTransferService::class);
        $this->settingsService ??= app(GeneralSettingsService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action'));

        if ($action === 'get') {
            $user = $this->requireRead($request);
            $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view enrollment records.');

            return $this->handleGet($request);
        }

        $user = $this->requireWrite($request);

        if ($action === 'create') {
            $this->requirePermission($user, 'Create:StudentEnrollment', 'You are not permitted to create student enrollments.');
        } elseif ($action === 'soft_delete' || $action === 'delete') {
            $this->requirePermission($user, 'Delete:StudentEnrollment', 'You are not permitted to delete enrollment records.');
        } elseif ($action === 'restore') {
            $this->requirePermission($user, 'Restore:StudentEnrollment', 'You are not permitted to restore enrollment records.');
        } elseif ($action === 'transfer_to_student') {
            $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to transfer enrollments between students.');
        } else {
            $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to update enrollment records.');
        }

        return match ($action) {
            'create' => $this->handleCreate($request, $user),
            'update' => $this->handleUpdate($request, $user),
            'update_status' => $this->handleUpdateStatus($request, $user),
            'soft_delete', 'delete' => $this->handleSoftDelete($request, $user),
            'restore' => $this->handleRestore($request, $user),
            'transfer_to_student' => $this->handleTransferToStudent($request, $user),
            default => Response::structured([
                'error' => true,
                'message' => "Unsupported action '{$action}'. Valid actions: create, update, update_status, soft_delete, restore, transfer_to_student, get.",
            ]),
        };
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['create', 'update', 'update_status', 'soft_delete', 'restore', 'transfer_to_student', 'get'])
                ->required()
                ->description('Enrollment lifecycle operation to perform.'),
            'enrollment_id' => $schema->integer()
                ->description('The internal StudentEnrollment ID (required for update, update_status, soft_delete, restore, transfer_to_student, and optional for get).'),
            'student_id' => $schema->string()
                ->description('Student database ID or student number for create or get.'),
            'target_student_id' => $schema->string()
                ->description('Destination student ID or student number when using transfer_to_student.'),
            'course_code' => $schema->string()
                ->description('Academic program code (e.g. BSIT, BSCS) for create or update.'),
            'course_id' => $schema->integer()
                ->description('Course/program database ID.'),
            'school_year' => $schema->string()
                ->description('School year string (e.g. "2025 - 2026"). Defaults to current active school year.'),
            'semester' => $schema->integer()
                ->enum([1, 2])
                ->description('Semester: 1 or 2. Defaults to current active semester.'),
            'academic_year' => $schema->integer()
                ->description('Student year level (1 to 5). Defaults to student current year level.'),
            'status' => $schema->string()
                ->enum(['enrolled', 'pending', 'cancelled', 'dropped', 'completed', 'transferred'])
                ->description('Enrollment status for create or update_status.'),
            'downpayment' => $schema->number()
                ->description('Assessed or received downpayment amount.'),
            'remarks' => $schema->string()
                ->description('Administrative remarks or explanatory notes.'),
            'reason' => $schema->string()
                ->description('Reason for status change, cancellation, soft delete, or student transfer.'),
            'include_trashed' => $schema->boolean()
                ->description('When true for get, includes soft-deleted enrollments.'),
            'preview' => $schema->boolean()
                ->description('When true for transfer_to_student, previews the transfer impact without committing.'),
            'confirm' => $schema->boolean()
                ->description('Required confirmation flag for destructive or reassignment actions (soft_delete, transfer_to_student).'),
            'idempotency_key' => $schema->string()
                ->description('Unique idempotency key for safe retries.'),
        ];
    }

    private function handleGet(Request $request): ResponseFactory
    {
        $enrollmentId = $request->get('enrollment_id') ? (int) $request->get('enrollment_id') : null;
        $studentId = $request->get('student_id');
        $includeTrashed = (bool) $request->get('include_trashed', false);

        $query = $includeTrashed ? StudentEnrollment::withTrashed() : StudentEnrollment::query();

        if ($enrollmentId !== null) {
            $enrollment = (clone $query)->with(['student', 'course', 'subjectsEnrolled.subject'])->find($enrollmentId);
            if (! $enrollment instanceof StudentEnrollment) {
                return Response::structured(['found' => false, 'message' => "Enrollment ID #{$enrollmentId} not found."]);
            }

            return Response::structured([
                'found' => true,
                'enrollment' => $this->formatEnrollment($enrollment),
            ]);
        }

        if (filled($studentId)) {
            $student = $this->resolveStudent((string) $studentId, withTrashed: $includeTrashed);
            if (! $student instanceof Student) {
                return Response::structured(['found' => false, 'message' => "Student '{$studentId}' not found."]);
            }

            $enrollments = (clone $query)
                ->where('student_id', (string) $student->id)
                ->with(['course', 'subjectsEnrolled.subject'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn (StudentEnrollment $e) => $this->formatEnrollment($e))
                ->all();

            return Response::structured([
                'found' => true,
                'student' => [
                    'id' => $student->id,
                    'student_number' => (string) $student->student_id,
                    'name' => $student->full_name,
                ],
                'count' => count($enrollments),
                'enrollments' => $enrollments,
            ]);
        }

        return Response::structured(['error' => true, 'message' => 'Either enrollment_id or student_id is required.']);
    }

    private function handleCreate(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $idempotencyKey = $request->get('idempotency_key');
        if (filled($idempotencyKey)) {
            $existing = StudentEnrollment::query()
                ->where('submission_idempotency_key', (string) $idempotencyKey)
                ->first();

            if ($existing instanceof StudentEnrollment) {
                return Response::structured([
                    'success' => true,
                    'action' => 'create',
                    'replayed' => true,
                    'idempotency_key' => $idempotencyKey,
                    'message' => "Enrollment #{$existing->id} was already created with this idempotency key.",
                    'enrollment' => $this->formatEnrollment($existing->fresh(['course', 'student'])),
                ]);
            }
        }

        $studentId = $request->get('student_id');
        if (blank($studentId)) {
            return Response::structured(['error' => true, 'message' => 'student_id is required to create an enrollment.']);
        }

        $student = $this->resolveStudent((string) $studentId);
        if (! $student instanceof Student) {
            return Response::structured(['error' => true, 'message' => "Student '{$studentId}' not found."]);
        }

        $school = $this->school();
        $schoolYear = (string) ($request->get('school_year') ?: $this->settingsService->getCurrentSchoolYearString());
        $semester = (int) ($request->get('semester') ?: $this->settingsService->getCurrentSemester());

        $courseId = null;
        if ($request->get('course_id')) {
            $courseId = (int) $request->get('course_id');
        } elseif ($request->get('course_code')) {
            $code = mb_strtoupper(mb_trim((string) $request->get('course_code')));
            $course = Course::query()->where('code', $code)->first();
            $courseId = $course?->id;
        } else {
            $courseId = $student->course_id;
        }

        $status = (string) ($request->get('status') ?: 'enrolled');
        $academicYear = (int) ($request->get('academic_year') ?: ($student->academic_year ?: 1));
        $downpayment = (float) ($request->get('downpayment') ?: 0.0);
        $remarks = (string) ($request->get('remarks') ?? 'Created via MCP ManageEnrollmentTool.');

        $enrollment = DB::transaction(function () use ($student, $school, $courseId, $schoolYear, $semester, $academicYear, $status, $downpayment, $remarks, $actor, $idempotencyKey): StudentEnrollment {
            $created = StudentEnrollment::query()->create([
                'student_id' => (string) $student->id,
                'school_id' => $school->id,
                'course_id' => $courseId,
                'school_year' => $schoolYear,
                'semester' => $semester,
                'academic_year' => $academicYear,
                'status' => $status,
                'downpayment' => $downpayment,
                'remarks' => $remarks,
                'current_step_key' => 'registration',
                'workflow_runtime' => StudentEnrollment::WorkflowRuntimePolicyV1,
                'submission_idempotency_key' => $idempotencyKey ?: (string) \Illuminate\Support\Str::uuid(),
            ]);

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $created->id,
                'actor_id' => $actor->id,
                'event_type' => 'enrollment_created',
                'reason' => 'Official enrollment created via administrative MCP.',
                'from_step_key' => null,
                'to_step_key' => 'registration',
                'status' => $status,
                'idempotency_key' => $idempotencyKey ? "enrollment:create:{$idempotencyKey}" : null,
            ]);

            return $created;
        });

        return Response::structured([
            'success' => true,
            'action' => 'create',
            'replayed' => false,
            'idempotency_key' => $idempotencyKey,
            'message' => "Successfully created enrollment #{$enrollment->id} for {$student->full_name} ({$schoolYear} Sem {$semester}).",
            'enrollment' => $this->formatEnrollment($enrollment->fresh(['course', 'student'])),
        ]);
    }

    private function handleUpdate(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $enrollmentId = (int) $request->get('enrollment_id');
        $enrollment = StudentEnrollment::query()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return Response::structured(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
        }

        $fields = [];
        if ($request->has('course_code')) {
            $code = mb_strtoupper(mb_trim((string) $request->get('course_code')));
            $course = Course::query()->where('code', $code)->first();
            if ($course) {
                $fields['course_id'] = $course->id;
            }
        } elseif ($request->has('course_id')) {
            $fields['course_id'] = (int) $request->get('course_id');
        }

        if ($request->has('school_year')) {
            $fields['school_year'] = (string) $request->get('school_year');
        }
        if ($request->has('semester')) {
            $fields['semester'] = (int) $request->get('semester');
        }
        if ($request->has('academic_year')) {
            $fields['academic_year'] = (int) $request->get('academic_year');
        }
        if ($request->has('downpayment')) {
            $fields['downpayment'] = (float) $request->get('downpayment');
        }
        if ($request->has('remarks')) {
            $fields['remarks'] = (string) $request->get('remarks');
        }

        if ($fields === []) {
            return Response::structured(['error' => true, 'message' => 'No updatable fields provided.']);
        }

        $enrollment->update($fields);

        EnrollmentWorkflowEvent::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'actor_id' => $actor->id,
            'event_type' => 'enrollment_updated',
            'reason' => (string) ($request->get('reason') ?? 'Enrollment details updated via administrative MCP.'),
            'from_step_key' => $enrollment->current_step_key,
            'to_step_key' => $enrollment->current_step_key,
            'status' => $enrollment->status,
        ]);

        return Response::structured([
            'success' => true,
            'action' => 'update',
            'message' => "Enrollment #{$enrollment->id} updated successfully.",
            'enrollment' => $this->formatEnrollment($enrollment->fresh(['course', 'student'])),
        ]);
    }

    private function handleUpdateStatus(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $enrollmentId = (int) $request->get('enrollment_id');
        $newStatus = mb_strtolower((string) $request->get('status'));
        $reason = (string) ($request->get('reason') ?? 'Status updated via administrative MCP.');

        $enrollment = StudentEnrollment::query()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return Response::structured(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
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

        return Response::structured([
            'success' => true,
            'action' => 'update_status',
            'message' => "Enrollment #{$enrollment->id} status updated from '{$oldStatus}' to '{$newStatus}'.",
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'enrollment_id' => $enrollment->id,
        ]);
    }

    private function handleSoftDelete(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $enrollmentId = (int) $request->get('enrollment_id');
        $reason = (string) ($request->get('reason') ?? 'Soft-deleted via administrative MCP.');

        $enrollment = StudentEnrollment::query()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return Response::structured(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
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

        return Response::structured([
            'success' => true,
            'action' => 'soft_delete',
            'message' => "Enrollment #{$enrollmentId} was successfully soft-deleted.",
            'enrollment_id' => $enrollmentId,
            'reason' => $reason,
        ]);
    }

    private function handleRestore(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $enrollmentId = (int) $request->get('enrollment_id');
        $enrollment = StudentEnrollment::withTrashed()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return Response::structured(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found (including trashed)."]);
        }

        if (! $enrollment->trashed()) {
            return Response::structured([
                'success' => true,
                'action' => 'restore',
                'message' => "Enrollment #{$enrollmentId} is already active.",
                'already_active' => true,
            ]);
        }

        $enrollment->restore();

        EnrollmentWorkflowEvent::query()->create([
            'student_enrollment_id' => $enrollment->id,
            'actor_id' => $actor->id,
            'event_type' => 'enrollment_restored',
            'reason' => (string) ($request->get('reason') ?? 'Restored from trash via administrative MCP.'),
            'from_step_key' => $enrollment->current_step_key,
            'to_step_key' => $enrollment->current_step_key,
            'status' => $enrollment->status,
        ]);

        return Response::structured([
            'success' => true,
            'action' => 'restore',
            'message' => "Enrollment #{$enrollmentId} restored successfully.",
            'enrollment' => $this->formatEnrollment($enrollment->fresh(['course', 'student'])),
        ]);
    }

    private function handleTransferToStudent(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $enrollmentId = (int) $request->get('enrollment_id');
        $targetId = (string) $request->get('target_student_id');

        if (blank($targetId)) {
            return Response::structured(['error' => true, 'message' => 'target_student_id is required to reassign an enrollment.']);
        }

        $enrollment = StudentEnrollment::withTrashed()->find($enrollmentId);
        if (! $enrollment instanceof StudentEnrollment) {
            return Response::structured(['error' => true, 'message' => "Enrollment #{$enrollmentId} not found."]);
        }

        $sourceStudent = $enrollment->student;
        if (! $sourceStudent instanceof Student) {
            return Response::structured(['error' => true, 'message' => "Source student record for enrollment #{$enrollmentId} could not be resolved."]);
        }

        $targetStudent = $this->resolveStudent($targetId, withTrashed: true);
        if (! $targetStudent instanceof Student) {
            return Response::structured(['error' => true, 'message' => "Target student '{$targetId}' not found."]);
        }

        $options = [
            'enrollment_id' => $enrollment->id,
            'reason' => (string) ($request->get('reason') ?? "Transfer enrollment #{$enrollment->id} to {$targetStudent->full_name}."),
        ];

        try {
            $preview = (bool) $request->get('preview', false);
            $confirm = (bool) $request->get('confirm', false);

            if ($preview || ! $confirm) {
                $previewData = $this->transferService->preview($sourceStudent, $targetStudent, $options);

                return Response::structured([
                    'success' => true,
                    'action' => 'transfer_enrollment_preview',
                    'confirmation_required' => ! $confirm,
                    'message' => "Review enrollment reassignment details below. Set confirm=true to move enrollment #{$enrollment->id} to {$targetStudent->full_name}.",
                    ...$previewData,
                ]);
            }

            $result = $this->transferService->transfer($sourceStudent, $targetStudent, $actor, $options);

            return Response::structured($result);
        } catch (Throwable $e) {
            return Response::structured(['error' => true, 'message' => $e->getMessage()]);
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
