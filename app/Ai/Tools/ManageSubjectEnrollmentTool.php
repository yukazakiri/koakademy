<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\ClassEnrollment;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class ManageSubjectEnrollmentTool implements Tool
{
    use InteractsWithApprovals;

    public function __construct(
        private ?EnrollmentBillingService $billingService = null,
    ) {
        $this->billingService ??= app(EnrollmentBillingService::class);
    }

    public function description(): Stringable|string
    {
        return 'Enroll students in subjects, drop subjects, update grades, modify billing/modular details, transfer subject enrollments between students, or inspect subject enrollments. Modifications require administrator confirmation.';
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
                return json_encode(['error' => true, 'message' => 'You are not permitted to view subject enrollments.']);
            }

            return $this->handleGet($request);
        }

        if (! $user->hasRole('super_admin') && ! $user->can('Update:StudentEnrollment')) {
            return json_encode(['error' => true, 'message' => 'You are not permitted to modify subject enrollments.']);
        }

        return match ($action) {
            'enroll' => $this->handleEnroll($request, $user),
            'drop' => $this->handleDrop($request, $user),
            'update_grade' => $this->handleUpdateGrade($request, $user),
            'update_details' => $this->handleUpdateDetails($request, $user),
            'transfer_to_student' => $this->handleTransferToStudent($request, $user),
            'delete' => $this->handleDelete($request, $user),
            default => json_encode(['error' => true, 'message' => "Unknown action '{$action}'. Supported: enroll, drop, update_grade, update_details, transfer_to_student, delete, get."]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->enum(['enroll', 'drop', 'update_grade', 'update_details', 'transfer_to_student', 'delete', 'get'])
                ->required()
                ->description('Subject enrollment operation to perform.'),
            'subject_enrollment_id' => $schema->integer()
                ->description('Database ID of the subject_enrollments record.'),
            'enrollment_id' => $schema->integer()
                ->description('Target StudentEnrollment record ID.'),
            'student_id' => $schema->string()
                ->description('Student database ID or student number.'),
            'target_student_id' => $schema->string()
                ->description('Destination student ID or student number for transfer.'),
            'target_enrollment_id' => $schema->integer()
                ->description('Destination enrollment ID on target student when transferring.'),
            'subject_id' => $schema->integer()
                ->description('Subject database ID to enroll.'),
            'subject_code' => $schema->string()
                ->description('Subject code (e.g. "CS101", "MATH-1") to resolve.'),
            'class_id' => $schema->integer()
                ->description('Class section ID to link with this subject enrollment.'),
            'grade' => $schema->number()
                ->description('Numerical grade (e.g. 1.25, 88.5, 3.0).'),
            'grade_symbol' => $schema->string()
                ->description('Grade symbol or letter (e.g. "1.00", "A", "INC", "DRP").'),
            'remarks' => $schema->string()
                ->description('Academic remarks (e.g. "Passed", "Failed", "Incomplete").'),
            'is_modular' => $schema->boolean()
                ->description('Whether the subject enrollment is modular.'),
            'is_credited' => $schema->boolean()
                ->description('Whether this subject was credited from prior institution.'),
            'exclude_from_tuition' => $schema->boolean()
                ->description('When true, excludes subject units/fees from student tuition assessment.'),
            'lecture_fee' => $schema->number()
                ->description('Custom lecture fee amount for this subject enrollment.'),
            'laboratory_fee' => $schema->number()
                ->description('Custom laboratory fee amount for this subject enrollment.'),
            'reason' => $schema->string()
                ->description('Reason for dropping, deleting, or transferring subject enrollment.'),
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

        if ($action === 'enroll') {
            $subj = $request['subject_code'] ?? ($request['subject_id'] ?? 'subject');
            $student = $request['student_id'] ?? 'student';

            return Approval::required("Enroll student {$student} into subject {$subj}?");
        }

        if ($action === 'drop') {
            $id = $request['subject_enrollment_id'] ?? 'unknown';
            $reason = ! empty($request['reason']) ? " Reason: {$request['reason']}" : '';

            return Approval::required("Drop subject enrollment #{$id}?{$reason}");
        }

        if ($action === 'update_grade') {
            $id = $request['subject_enrollment_id'] ?? 'unknown';

            return Approval::required("Update academic grades on subject enrollment #{$id}?");
        }

        if ($action === 'update_details') {
            $id = $request['subject_enrollment_id'] ?? 'unknown';

            return Approval::required("Modify fees or modular/credited details on subject enrollment #{$id}?");
        }

        if ($action === 'transfer_to_student') {
            if (request()->boolean('preview', false) || ($request['preview'] ?? false)) {
                return false;
            }

            $id = $request['subject_enrollment_id'] ?? 'unknown';
            $target = $request['target_student_id'] ?? 'target student';

            return Approval::required("Transfer subject enrollment #{$id} to student {$target}?");
        }

        if ($action === 'delete') {
            $id = $request['subject_enrollment_id'] ?? 'unknown';

            return Approval::required("Permanently delete subject enrollment #{$id}?");
        }

        return false;
    }

    private function handleGet(Request $request): string
    {
        $id = $request['subject_enrollment_id'] ? (int) $request['subject_enrollment_id'] : null;
        $studentId = $request['student_id'] ?? null;

        if ($id !== null) {
            $record = SubjectEnrollment::query()->with(['student', 'enrollment', 'subject', 'class'])->find($id);
            if (! $record instanceof SubjectEnrollment) {
                return json_encode(['found' => false, 'message' => "Subject enrollment #{$id} not found."]);
            }

            return json_encode([
                'found' => true,
                'subject_enrollment' => $this->formatSubjectEnrollment($record),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        if (filled($studentId)) {
            $student = $this->resolveStudent((string) $studentId, withTrashed: true);
            if (! $student instanceof Student) {
                return json_encode(['found' => false, 'message' => "Student '{$studentId}' not found."]);
            }

            $records = SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->with(['subject', 'class', 'enrollment'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn (SubjectEnrollment $se) => $this->formatSubjectEnrollment($se))
                ->all();

            return json_encode([
                'found' => true,
                'student' => [
                    'id' => $student->id,
                    'student_number' => (string) $student->student_id,
                    'name' => $student->full_name,
                ],
                'count' => count($records),
                'subjects' => $records,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return json_encode(['error' => true, 'message' => 'Either subject_enrollment_id or student_id is required.']);
    }

    private function handleEnroll(Request $request, User $actor): string
    {
        $studentId = $request['student_id'] ?? null;
        $enrollmentId = $request['enrollment_id'] ? (int) $request['enrollment_id'] : null;

        $student = null;
        $enrollment = null;

        if ($enrollmentId !== null) {
            $enrollment = StudentEnrollment::query()->find($enrollmentId);
            if ($enrollment instanceof StudentEnrollment) {
                $student = $enrollment->student;
            }
        } elseif (filled($studentId)) {
            $student = $this->resolveStudent((string) $studentId);
            if ($student instanceof Student) {
                $enrollment = $student->studentEnrollments()->latest('id')->first();
            }
        }

        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => 'Valid student or enrollment record required.']);
        }

        // If no enrollment exists for the student, establish an official enrollment first
        if (! $enrollment instanceof StudentEnrollment) {
            $settings = app(\App\Services\GeneralSettingsService::class);
            $currentSy = (string) ($settings->getCurrentSchoolYearString() ?: date('Y').' - '.(date('Y') + 1));
            $currentSem = (int) ($settings->getCurrentSemester() ?: 1);
            $schoolId = $student->school_id ?: 1;

            $enrollment = StudentEnrollment::query()->create([
                'student_id' => (string) $student->id,
                'school_id' => $schoolId,
                'course_id' => $student->course_id,
                'academic_year' => $student->academic_year ?: 1,
                'semester' => $currentSem,
                'school_year' => $currentSy,
                'status' => 'enrolled',
                'workflow_runtime' => StudentEnrollment::WorkflowRuntimePolicyV1,
            ]);
        }

        $subject = null;
        if (! empty($request['subject_id'])) {
            $subject = Subject::query()->find((int) $request['subject_id']);
        } elseif (! empty($request['subject_code'])) {
            $code = mb_strtoupper(mb_trim((string) $request['subject_code']));
            $subject = Subject::query()->where('code', $code)->first();
        }

        if (! $subject instanceof Subject) {
            return json_encode(['error' => true, 'message' => 'Valid subject_id or subject_code is required.']);
        }

        $schoolId = $student->school_id ?: 1;
        $classId = ! empty($request['class_id']) ? (int) $request['class_id'] : null;

        $createdRecord = DB::transaction(function () use ($student, $enrollment, $subject, $classId, $schoolId, $request, $actor): SubjectEnrollment {
            $record = SubjectEnrollment::query()->create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment?->id ?? 0,
                'subject_id' => $subject->id,
                'class_id' => $classId,
                'school_id' => $schoolId,
                'school_year' => $enrollment?->school_year ?? 'Current',
                'semester' => $enrollment?->semester ?? 1,
                'academic_year' => $enrollment?->academic_year ?? $student->academic_year ?? 1,
                'is_modular' => (bool) ($request['is_modular'] ?? false),
                'is_credited' => (bool) ($request['is_credited'] ?? false),
                'exclude_from_tuition' => (bool) ($request['exclude_from_tuition'] ?? false),
                'lecture_fee' => isset($request['lecture_fee']) ? (float) $request['lecture_fee'] : 0.0,
                'laboratory_fee' => isset($request['laboratory_fee']) ? (float) $request['laboratory_fee'] : 0.0,
            ]);

            if ($classId !== null) {
                ClassEnrollment::query()->firstOrCreate([
                    'student_id' => $student->id,
                    'class_id' => $classId,
                ], [
                    'school_id' => $schoolId,
                    'status' => true,
                ]);
            }

            if ($enrollment instanceof StudentEnrollment) {
                $this->billingService->recalculateEnrollmentTuition($enrollment);
            }

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $enrollment?->id ?? 0,
                'actor_id' => $actor->id,
                'event_type' => 'subject_enrolled',
                'reason' => "Enrolled into {$subject->code} via AI ManageSubjectEnrollmentTool.",
                'result' => ['subject_id' => $subject->id, 'class_id' => $classId],
            ]);

            return $record;
        });

        return json_encode([
            'success' => true,
            'action' => 'enroll',
            'message' => "Enrolled {$student->full_name} into {$subject->code} successfully.",
            'subject_enrollment' => $this->formatSubjectEnrollment($createdRecord->fresh(['subject', 'class'])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleDrop(Request $request, User $actor): string
    {
        $id = (int) ($request['subject_enrollment_id'] ?? 0);
        $reason = (string) ($request['reason'] ?? 'Subject dropped via AI copilot.');

        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return json_encode(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $enrollment = $record->enrollment;
        $classId = $record->class_id;
        $studentId = $record->student_id;
        $subjectCode = $record->subject?->code ?? 'N/A';

        DB::transaction(function () use ($record, $enrollment, $classId, $studentId, $reason, $actor): void {
            if ($classId !== null) {
                ClassEnrollment::query()
                    ->where('student_id', $studentId)
                    ->where('class_id', $classId)
                    ->delete();
            }

            $record->delete();

            if ($enrollment instanceof StudentEnrollment) {
                $this->billingService->recalculateEnrollmentTuition($enrollment);
            }

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $enrollment?->id ?? 0,
                'actor_id' => $actor->id,
                'event_type' => 'subject_dropped',
                'reason' => $reason,
            ]);
        });

        return json_encode([
            'success' => true,
            'action' => 'drop',
            'message' => "Subject {$subjectCode} (enrollment #{$id}) dropped successfully.",
            'reason' => $reason,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleUpdateGrade(Request $request, User $actor): string
    {
        $id = (int) ($request['subject_enrollment_id'] ?? 0);
        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return json_encode(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $fields = [];
        if (isset($request['grade'])) {
            $fields['grade'] = (float) $request['grade'];
        }
        if (isset($request['grade_symbol'])) {
            $fields['grade_symbol'] = (string) $request['grade_symbol'];
        }
        if (isset($request['remarks'])) {
            $fields['remarks'] = (string) $request['remarks'];
        }

        if ($fields === []) {
            return json_encode(['error' => true, 'message' => 'No grade fields provided to update.']);
        }

        $record->update($fields);

        if ($record->class_id !== null) {
            $classEnrollment = ClassEnrollment::query()
                ->where('student_id', $record->student_id)
                ->where('class_id', $record->class_id)
                ->first();
            if ($classEnrollment) {
                if (isset($fields['grade'])) {
                    $classEnrollment->total_average = $fields['grade'];
                }
                if (isset($fields['remarks'])) {
                    $classEnrollment->remarks = $fields['remarks'];
                }
                $classEnrollment->save();
            }
        }

        return json_encode([
            'success' => true,
            'action' => 'update_grade',
            'message' => "Grades for subject enrollment #{$id} updated successfully.",
            'subject_enrollment' => $this->formatSubjectEnrollment($record->fresh(['subject', 'class'])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleUpdateDetails(Request $request, User $actor): string
    {
        $id = (int) ($request['subject_enrollment_id'] ?? 0);
        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return json_encode(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $fields = [];
        if (isset($request['is_modular'])) {
            $fields['is_modular'] = (bool) $request['is_modular'];
        }
        if (isset($request['is_credited'])) {
            $fields['is_credited'] = (bool) $request['is_credited'];
        }
        if (isset($request['exclude_from_tuition'])) {
            $fields['exclude_from_tuition'] = (bool) $request['exclude_from_tuition'];
        }
        if (isset($request['lecture_fee'])) {
            $fields['lecture_fee'] = (float) $request['lecture_fee'];
        }
        if (isset($request['laboratory_fee'])) {
            $fields['laboratory_fee'] = (float) $request['laboratory_fee'];
        }

        if ($fields === []) {
            return json_encode(['error' => true, 'message' => 'No detail fields provided to update.']);
        }

        $record->update($fields);

        if ($record->enrollment instanceof StudentEnrollment) {
            $this->billingService->recalculateEnrollmentTuition($record->enrollment);
        }

        return json_encode([
            'success' => true,
            'action' => 'update_details',
            'message' => "Subject enrollment #{$id} details updated successfully.",
            'subject_enrollment' => $this->formatSubjectEnrollment($record->fresh(['subject', 'class'])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleTransferToStudent(Request $request, User $actor): string
    {
        $id = (int) ($request['subject_enrollment_id'] ?? 0);
        $targetId = (string) ($request['target_student_id'] ?? '');
        $targetEnrollmentId = ! empty($request['target_enrollment_id']) ? (int) $request['target_enrollment_id'] : null;

        if (blank($targetId)) {
            return json_encode(['error' => true, 'message' => 'target_student_id is required.']);
        }

        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return json_encode(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $target = $this->resolveStudent($targetId, withTrashed: true);
        if (! $target instanceof Student) {
            return json_encode(['error' => true, 'message' => "Target student '{$targetId}' not found."]);
        }

        // Resolve or validate enrollment owned by target student
        $targetEnrollment = null;
        if ($targetEnrollmentId !== null) {
            $targetEnrollment = StudentEnrollment::query()
                ->where('id', $targetEnrollmentId)
                ->where('student_id', (string) $target->id)
                ->first();

            if (! $targetEnrollment instanceof StudentEnrollment) {
                return json_encode([
                    'error' => true,
                    'message' => "Target enrollment #{$targetEnrollmentId} does not belong to target student #{$target->student_id}.",
                ]);
            }
        } else {
            $targetEnrollment = StudentEnrollment::query()
                ->where('student_id', (string) $target->id)
                ->where('school_year', $record->school_year)
                ->where('semester', $record->semester)
                ->first()
                ?? StudentEnrollment::query()
                    ->where('student_id', (string) $target->id)
                    ->latest('id')
                    ->first();

            if (! $targetEnrollment instanceof StudentEnrollment) {
                $targetEnrollment = StudentEnrollment::query()->create([
                    'student_id' => (string) $target->id,
                    'school_id' => $target->school_id ?: 1,
                    'course_id' => $target->course_id,
                    'academic_year' => $target->academic_year ?: 1,
                    'semester' => $record->semester ?: 1,
                    'school_year' => $record->school_year ?: 'Current',
                    'status' => 'enrolled',
                    'workflow_runtime' => StudentEnrollment::WorkflowRuntimePolicyV1,
                ]);
            }
        }

        $targetEnrollmentIdResolved = $targetEnrollment->id;

        if ($request['preview'] ?? false) {
            return json_encode([
                'success' => true,
                'action' => 'transfer_subject_preview',
                'subject' => $record->subject?->code,
                'current_student_id' => $record->student_id,
                'target_student_id' => $target->id,
                'target_student_name' => $target->full_name,
                'target_enrollment_id' => $targetEnrollmentIdResolved,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        $oldStudentId = $record->student_id;
        $oldEnrollment = $record->enrollment;

        DB::transaction(function () use ($record, $target, $targetEnrollmentIdResolved, $oldEnrollment, $oldStudentId): void {
            $classId = $record->class_id;

            $record->student_id = $target->id;
            $record->enrollment_id = $targetEnrollmentIdResolved;
            $record->save();

            if ($classId !== null) {
                ClassEnrollment::query()
                    ->where('student_id', $oldStudentId)
                    ->where('class_id', $classId)
                    ->update(['student_id' => $target->id]);
            }

            if ($oldEnrollment instanceof StudentEnrollment) {
                $this->billingService->recalculateEnrollmentTuition($oldEnrollment);
            }

            $newEnrollment = $record->enrollment;
            if ($newEnrollment instanceof StudentEnrollment && $newEnrollment->id !== $oldEnrollment?->id) {
                $this->billingService->recalculateEnrollmentTuition($newEnrollment);
            }
        });

        return json_encode([
            'success' => true,
            'action' => 'transfer_to_student',
            'message' => "Subject enrollment #{$id} transferred successfully to {$target->full_name}.",
            'subject_enrollment' => $this->formatSubjectEnrollment($record->fresh(['subject', 'class'])),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleDelete(Request $request, User $actor): string
    {
        $id = (int) ($request['subject_enrollment_id'] ?? 0);
        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return json_encode(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $enrollment = $record->enrollment;
        $classId = $record->class_id;
        $studentId = $record->student_id;

        DB::transaction(function () use ($record, $enrollment, $classId, $studentId): void {
            if ($classId !== null) {
                ClassEnrollment::query()
                    ->where('student_id', $studentId)
                    ->where('class_id', $classId)
                    ->delete();
            }

            $record->delete();

            if ($enrollment instanceof StudentEnrollment) {
                $this->billingService->recalculateEnrollmentTuition($enrollment);
            }
        });

        return json_encode([
            'success' => true,
            'action' => 'delete',
            'message' => "Subject enrollment #{$id} permanently removed.",
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** @return array<string, mixed> */
    private function formatSubjectEnrollment(SubjectEnrollment $record): array
    {
        return [
            'id' => $record->id,
            'student_id' => $record->student_id,
            'enrollment_id' => $record->enrollment_id,
            'subject_code' => $record->subject?->code ?? $record->external_subject_code ?? 'N/A',
            'subject_title' => $record->subject?->title ?? $record->external_subject_title ?? 'N/A',
            'section' => $record->class?->section ?? $record->section,
            'class_id' => $record->class_id,
            'grade' => $record->grade,
            'grade_symbol' => $record->grade_symbol,
            'remarks' => $record->remarks,
            'is_modular' => (bool) $record->is_modular,
            'is_credited' => (bool) $record->is_credited,
            'exclude_from_tuition' => (bool) $record->exclude_from_tuition,
            'lecture_fee' => (float) $record->lecture_fee,
            'laboratory_fee' => (float) $record->laboratory_fee,
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
