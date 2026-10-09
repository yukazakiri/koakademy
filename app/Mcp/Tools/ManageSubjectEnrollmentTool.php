<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\ClassEnrollment;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\EnrollmentBillingService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Manage the complete subject enrollment lifecycle: enroll student into subjects, drop subjects, update grades, update details (modular, credited, fees), transfer subject enrollment to another student, delete, or inspect records. Requires MCP write key and appropriate registrar permissions.')]
#[IsIdempotent]
final class ManageSubjectEnrollmentTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?EnrollmentBillingService $billingService = null,
    ) {
        $this->billingService ??= app(EnrollmentBillingService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action'));

        if ($action === 'get') {
            $user = $this->requireRead($request);
            $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view subject enrollments.');

            return $this->handleGet($request);
        }

        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to modify subject enrollments.');

        return match ($action) {
            'enroll' => $this->handleEnroll($request, $user),
            'drop' => $this->handleDrop($request, $user),
            'update_grade' => $this->handleUpdateGrade($request, $user),
            'update_details' => $this->handleUpdateDetails($request, $user),
            'transfer_to_student' => $this->handleTransferToStudent($request, $user),
            'delete' => $this->handleDelete($request, $user),
            default => Response::structured([
                'error' => true,
                'message' => "Unsupported action '{$action}'. Valid: enroll, drop, update_grade, update_details, transfer_to_student, delete, get.",
            ]),
        };
    }

    /** @return array<string, Type> */
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
                ->description('Destination student ID or student number when transferring subject enrollment.'),
            'target_enrollment_id' => $schema->integer()
                ->description('Optional destination enrollment ID on target student when transferring.'),
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
            'confirm' => $schema->boolean()
                ->description('Confirmation flag for destructive or transfer operations.'),
        ];
    }

    private function handleGet(Request $request): ResponseFactory
    {
        $id = $request->get('subject_enrollment_id') ? (int) $request->get('subject_enrollment_id') : null;
        $studentId = $request->get('student_id');

        if ($id !== null) {
            $record = SubjectEnrollment::query()->with(['student', 'enrollment', 'subject', 'class'])->find($id);
            if (! $record instanceof SubjectEnrollment) {
                return Response::structured(['found' => false, 'message' => "Subject enrollment #{$id} not found."]);
            }

            return Response::structured([
                'found' => true,
                'subject_enrollment' => $this->formatSubjectEnrollment($record),
            ]);
        }

        if (filled($studentId)) {
            $student = $this->resolveStudent((string) $studentId, withTrashed: true);
            if (! $student instanceof Student) {
                return Response::structured(['found' => false, 'message' => "Student '{$studentId}' not found."]);
            }

            $records = SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->with(['subject', 'class', 'enrollment'])
                ->orderBy('id', 'desc')
                ->get()
                ->map(fn (SubjectEnrollment $se) => $this->formatSubjectEnrollment($se))
                ->all();

            return Response::structured([
                'found' => true,
                'student' => [
                    'id' => $student->id,
                    'student_number' => (string) $student->student_id,
                    'name' => $student->full_name,
                ],
                'count' => count($records),
                'subjects' => $records,
            ]);
        }

        return Response::structured(['error' => true, 'message' => 'Either subject_enrollment_id or student_id is required.']);
    }

    private function handleEnroll(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $studentId = $request->get('student_id');
        $enrollmentId = $request->get('enrollment_id') ? (int) $request->get('enrollment_id') : null;

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
            return Response::structured(['error' => true, 'message' => 'Valid student or enrollment record required.']);
        }

        $subject = null;
        if ($request->get('subject_id')) {
            $subject = Subject::query()->find((int) $request->get('subject_id'));
        } elseif ($request->get('subject_code')) {
            $code = mb_strtoupper(mb_trim((string) $request->get('subject_code')));
            $subject = Subject::query()->where('code', $code)->first();
        }

        if (! $subject instanceof Subject) {
            return Response::structured(['error' => true, 'message' => 'Valid subject_id or subject_code is required.']);
        }

        $school = $this->school();
        $classId = $request->get('class_id') ? (int) $request->get('class_id') : null;

        $createdRecord = DB::transaction(function () use ($student, $enrollment, $subject, $classId, $school, $request, $actor): SubjectEnrollment {
            $record = SubjectEnrollment::query()->create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment?->id ?? 0,
                'subject_id' => $subject->id,
                'class_id' => $classId,
                'school_id' => $school->id,
                'school_year' => $enrollment?->school_year ?? 'Current',
                'semester' => $enrollment?->semester ?? 1,
                'academic_year' => $enrollment?->academic_year ?? $student->academic_year ?? 1,
                'is_modular' => (bool) $request->get('is_modular', false),
                'is_credited' => (bool) $request->get('is_credited', false),
                'exclude_from_tuition' => (bool) $request->get('exclude_from_tuition', false),
                'lecture_fee' => $request->get('lecture_fee') ? (float) $request->get('lecture_fee') : 0.0,
                'laboratory_fee' => $request->get('laboratory_fee') ? (float) $request->get('laboratory_fee') : 0.0,
            ]);

            if ($classId !== null) {
                ClassEnrollment::query()->firstOrCreate([
                    'student_id' => $student->id,
                    'class_id' => $classId,
                ], [
                    'school_id' => $school->id,
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
                'reason' => "Enrolled into {$subject->code} via MCP ManageSubjectEnrollmentTool.",
                'result' => ['subject_id' => $subject->id, 'class_id' => $classId],
            ]);

            return $record;
        });

        return Response::structured([
            'success' => true,
            'action' => 'enroll',
            'message' => "Enrolled {$student->full_name} into {$subject->code} successfully.",
            'subject_enrollment' => $this->formatSubjectEnrollment($createdRecord->fresh(['subject', 'class'])),
        ]);
    }

    private function handleDrop(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $id = (int) $request->get('subject_enrollment_id');
        $reason = (string) ($request->get('reason') ?? 'Subject dropped via administrative MCP.');

        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return Response::structured(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
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

        return Response::structured([
            'success' => true,
            'action' => 'drop',
            'message' => "Subject {$subjectCode} (enrollment #{$id}) dropped successfully.",
            'reason' => $reason,
        ]);
    }

    private function handleUpdateGrade(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $id = (int) $request->get('subject_enrollment_id');
        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return Response::structured(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $fields = [];
        if ($request->has('grade')) {
            $fields['grade'] = (float) $request->get('grade');
        }
        if ($request->has('grade_symbol')) {
            $fields['grade_symbol'] = (string) $request->get('grade_symbol');
        }
        if ($request->has('remarks')) {
            $fields['remarks'] = (string) $request->get('remarks');
        }

        if ($fields === []) {
            return Response::structured(['error' => true, 'message' => 'No grade fields provided to update.']);
        }

        $record->update($fields);

        // Synchronize linked class enrollment if present
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

        return Response::structured([
            'success' => true,
            'action' => 'update_grade',
            'message' => "Grades for subject enrollment #{$id} updated successfully.",
            'subject_enrollment' => $this->formatSubjectEnrollment($record->fresh(['subject', 'class'])),
        ]);
    }

    private function handleUpdateDetails(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $id = (int) $request->get('subject_enrollment_id');
        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return Response::structured(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $fields = [];
        if ($request->has('is_modular')) {
            $fields['is_modular'] = (bool) $request->get('is_modular');
        }
        if ($request->has('is_credited')) {
            $fields['is_credited'] = (bool) $request->get('is_credited');
        }
        if ($request->has('exclude_from_tuition')) {
            $fields['exclude_from_tuition'] = (bool) $request->get('exclude_from_tuition');
        }
        if ($request->has('lecture_fee')) {
            $fields['lecture_fee'] = (float) $request->get('lecture_fee');
        }
        if ($request->has('laboratory_fee')) {
            $fields['laboratory_fee'] = (float) $request->get('laboratory_fee');
        }

        if ($fields === []) {
            return Response::structured(['error' => true, 'message' => 'No detail fields provided to update.']);
        }

        $record->update($fields);

        if ($record->enrollment instanceof StudentEnrollment) {
            $this->billingService->recalculateEnrollmentTuition($record->enrollment);
        }

        return Response::structured([
            'success' => true,
            'action' => 'update_details',
            'message' => "Subject enrollment #{$id} details updated successfully.",
            'subject_enrollment' => $this->formatSubjectEnrollment($record->fresh(['subject', 'class'])),
        ]);
    }

    private function handleTransferToStudent(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $id = (int) $request->get('subject_enrollment_id');
        $targetId = (string) $request->get('target_student_id');
        $targetEnrollmentId = $request->get('target_enrollment_id') ? (int) $request->get('target_enrollment_id') : null;

        if (blank($targetId)) {
            return Response::structured(['error' => true, 'message' => 'target_student_id is required.']);
        }

        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return Response::structured(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
        }

        $target = $this->resolveStudent($targetId, withTrashed: true);
        if (! $target instanceof Student) {
            return Response::structured(['error' => true, 'message' => "Target student '{$targetId}' not found."]);
        }

        $confirm = (bool) $request->get('confirm', false);
        if (! $confirm) {
            return Response::structured([
                'success' => true,
                'action' => 'transfer_subject_preview',
                'confirmation_required' => true,
                'message' => "Transfer subject {$record->subject?->code} from student #{$record->student_id} to student {$target->full_name} (#{$target->student_id}). Set confirm=true to commit.",
                'subject' => $record->subject?->code,
                'current_student_id' => $record->student_id,
                'target_student_id' => $target->id,
            ]);
        }

        $oldStudentId = $record->student_id;
        $oldEnrollment = $record->enrollment;

        DB::transaction(function () use ($record, $target, $targetEnrollmentId, $oldEnrollment, $oldStudentId): void {
            $classId = $record->class_id;

            $record->student_id = $target->id;
            if ($targetEnrollmentId !== null) {
                $record->enrollment_id = $targetEnrollmentId;
            }
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

        return Response::structured([
            'success' => true,
            'action' => 'transfer_to_student',
            'message' => "Subject enrollment #{$id} transferred successfully to {$target->full_name}.",
            'subject_enrollment' => $this->formatSubjectEnrollment($record->fresh(['subject', 'class'])),
        ]);
    }

    private function handleDelete(Request $request, \App\Models\User $actor): ResponseFactory
    {
        $id = (int) $request->get('subject_enrollment_id');
        $record = SubjectEnrollment::query()->find($id);
        if (! $record instanceof SubjectEnrollment) {
            return Response::structured(['error' => true, 'message' => "Subject enrollment #{$id} not found."]);
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

        return Response::structured([
            'success' => true,
            'action' => 'delete',
            'message' => "Subject enrollment #{$id} permanently removed.",
        ]);
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
