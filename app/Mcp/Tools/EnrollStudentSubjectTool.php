<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Classes;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\ClassEnrollmentService;
use BackedEnum;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Enroll a student in an academic subject under an enrollment record, optionally assigning a scheduled class section. Requires MCP write access, Update:StudentEnrollment permission, and an idempotency key.')]
#[IsIdempotent]
final class EnrollStudentSubjectTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?ClassEnrollmentService $classEnrollments = null)
    {
        $this->classEnrollments ??= app(ClassEnrollmentService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to enroll students in subjects.');

        $validated = $request->validate([
            'enrollment_id' => ['nullable', 'integer', 'min:1'],
            'student_id' => ['nullable'],
            'subject_id' => ['nullable', 'integer', 'min:1'],
            'subject_code' => ['nullable', 'string', 'max:50'],
            'class_id' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:50'],
            'is_modular' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'min:1', 'max:96'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.subject_id' => ['nullable', 'integer'],
            'subjects.*.subject_code' => ['nullable', 'string'],
            'subjects.*.class_id' => ['nullable', 'integer'],
            'subjects.*.section' => ['nullable', 'string'],
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? (string) \Illuminate\Support\Str::uuid();

        $enrollment = null;
        if (filled($validated['enrollment_id'] ?? null)) {
            $enrollment = StudentEnrollment::query()->findOrFail($validated['enrollment_id']);
        } elseif (filled($validated['student_id'] ?? null)) {
            $student = $this->resolveStudent((string) $validated['student_id']);
            if ($student instanceof \App\Models\Student) {
                $enrollment = $student->studentEnrollments()->latest('id')->first();
            }
        }

        if (! $enrollment instanceof StudentEnrollment) {
            throw ValidationException::withMessages([
                'enrollment_id' => 'Could not find an enrollment record for the specified student. Create an enrollment first.',
            ]);
        }

        if (! $enrollment->belongsToCurrentSchool()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The enrollment record does not belong to the selected school.');
        }

        // Handle batch of subjects if provided
        if (! empty($validated['subjects'])) {
            $enrolledItems = [];
            $totalUnits = 0;
            foreach ($validated['subjects'] as $subInput) {
                $sId = $subInput['subject_id'] ?? null;
                $sCode = $subInput['subject_code'] ?? null;
                $subject = null;
                if ($sId) {
                    $subject = Subject::query()->find($sId);
                } elseif ($sCode) {
                    $subject = Subject::query()->where('code', mb_strtoupper(mb_trim((string) $sCode)))
                        ->when($enrollment->course_id, fn ($q) => $q->where('course_id', $enrollment->course_id))
                        ->first() ?? Subject::query()->where('code', mb_strtoupper(mb_trim((string) $sCode)))->first();
                }

                if (! $subject instanceof Subject) {
                    continue;
                }

                $existing = SubjectEnrollment::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('subject_id', $subject->id)
                    ->first();

                if ($existing instanceof SubjectEnrollment) {
                    $enrolledItems[] = [
                        'subject_id' => $subject->id,
                        'code' => $subject->code,
                        'title' => $subject->title,
                        'units' => $subject->units,
                        'replayed' => true,
                    ];
                    $totalUnits += $subject->units;

                    continue;
                }

                $cId = $subInput['class_id'] ?? null;
                $cSec = $subInput['section'] ?? null;
                $se = SubjectEnrollment::query()->create([
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student_id,
                    'subject_id' => $subject->id,
                    'class_id' => $cId,
                    'section' => $cSec,
                    'school_year' => $enrollment->school_year,
                    'semester' => $enrollment->semester,
                    'is_modular' => false,
                ]);

                $enrolledItems[] = [
                    'id' => $se->id,
                    'subject_id' => $subject->id,
                    'code' => $subject->code,
                    'title' => $subject->title,
                    'units' => $subject->units,
                    'replayed' => false,
                ];
                $totalUnits += $subject->units;
            }

            return Response::structured([
                'success' => true,
                'action' => 'batch_enroll',
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'total_enrolled' => count($enrolledItems),
                'total_units' => $totalUnits,
                'subjects' => $enrolledItems,
            ]);
        }

        $subject = null;
        if (filled($validated['subject_id'] ?? null)) {
            $subject = Subject::query()->findOrFail($validated['subject_id']);
        } elseif (filled($validated['subject_code'] ?? null)) {
            $subject = Subject::query()->where('code', mb_strtoupper(mb_trim((string) $validated['subject_code'])))
                ->when($enrollment->course_id, fn ($q) => $q->where('course_id', $enrollment->course_id))
                ->first() ?? Subject::query()->where('code', mb_strtoupper(mb_trim((string) $validated['subject_code'])))->first();
        }

        if (! $subject instanceof Subject) {
            throw ValidationException::withMessages([
                'subject_id' => 'Subject could not be resolved. Please specify a valid subject_id or subject_code.',
            ]);
        }

        if ($enrollment->course_id !== null && (int) $subject->course_id !== (int) $enrollment->course_id) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Subject [{$subject->code}] does not belong to the enrollment program.");
        }

        $scopedKey = hash('sha256', "mcp:enroll-subject:{$enrollment->id}:{$subject->id}:{$idempotencyKey}");

        $existingEvent = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedKey)->first();
        if ($existingEvent instanceof EnrollmentWorkflowEvent) {
            $existingRecord = SubjectEnrollment::query()->find($existingEvent->result['subject_enrollment_id'] ?? null);
            if ($existingRecord instanceof SubjectEnrollment) {
                return $this->buildResponse($existingRecord, $idempotencyKey, true);
            }
        }

        // Avoid duplicate subject enrollment in the same term
        $existing = SubjectEnrollment::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('subject_id', $subject->id)
            ->first();

        if ($existing instanceof SubjectEnrollment) {
            return $this->buildResponse($existing, $idempotencyKey, true);
        }

        $classId = isset($validated['class_id']) ? (int) $validated['class_id'] : null;
        $classSection = $validated['section'] ?? null;

        if ($classId !== null) {
            $class = Classes::query()->lockForUpdate()->findOrFail($classId);

            if (! $class->belongsToCurrentSchool()) {
                throw new \Illuminate\Auth\Access\AuthorizationException('The specified class does not belong to the selected school.');
            }

            $classMatchesPeriod = Classes::query()->whereKey($class->id)
                ->forAcademicPeriod((string) $enrollment->school_year, (int) $enrollment->semester)
                ->exists();

            if (! $classMatchesPeriod) {
                throw ValidationException::withMessages([
                    'class_id' => "Class section [{$class->section}] is not scheduled for school year [{$enrollment->school_year}], semester [{$enrollment->semester}].",
                ]);
            }

            $classCode = mb_trim((string) $class->subject_code);
            $subjectCode = mb_trim((string) $subject->code);
            $subjectMatches = (int) $class->subject_id === (int) $subject->id
                || ($classCode !== '' && $subjectCode !== '' && $classCode === $subjectCode)
                || in_array((int) $subject->id, array_map(intval(...), $class->subject_ids ?? []), true);

            if (! $subjectMatches) {
                throw ValidationException::withMessages([
                    'class_id' => "Class section [{$class->section}] ({$class->subject_code}) does not match subject [{$subject->code}].",
                ]);
            }

            $enrolledCount = $class->class_enrollments()->where('status', true)->count();
            $alreadyEnrolledInClass = \App\Models\ClassEnrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $enrollment->student_id)
                ->exists();

            if (! $alreadyEnrolledInClass && (int) $class->maximum_slots > 0 && $enrolledCount >= (int) $class->maximum_slots) {
                throw ValidationException::withMessages([
                    'class_id' => "Class section [{$class->section}] has no available seats ({$enrolledCount}/{$class->maximum_slots}).",
                ]);
            }

            $classSection ??= $class->section;
        }

        $subjectEnrollment = DB::transaction(function () use ($enrollment, $subject, $classId, $classSection, $validated, $scopedKey, $user): SubjectEnrollment {
            $created = SubjectEnrollment::query()->create([
                'student_id' => $enrollment->student_id,
                'subject_id' => $subject->id,
                'enrollment_id' => $enrollment->id,
                'class_id' => $classId,
                'section' => $classSection,
                'academic_year' => $enrollment->academic_year ?? $subject->academic_year ?? 1,
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'classification' => $subject->classification instanceof BackedEnum ? $subject->classification->value : 'internal',
                'is_modular' => (bool) ($validated['is_modular'] ?? false),
                'enrolled_lecture_units' => $subject->lecture ?? 0,
                'enrolled_laboratory_units' => $subject->laboratory ?? 0,
                'school_id' => $enrollment->school_id,
            ]);

            if ($classId !== null && $enrollment->student_id) {
                $this->classEnrollments->enrollOnce((int) $enrollment->student_id, $classId, [
                    'status' => true,
                    'school_id' => $enrollment->school_id,
                ]);
            }

            if ($enrollment->studentTuition()->exists()) {
                app(\App\Services\EnrollmentBillingService::class)->recalculateEnrollmentTuition($enrollment);
            }

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $enrollment->id,
                'actor_id' => $user->id,
                'event_type' => 'subject_enrolled',
                'idempotency_key' => $scopedKey,
                'result' => [
                    'subject_enrollment_id' => $created->id,
                    'subject_code' => $subject->code,
                    'class_id' => $classId,
                ],
            ]);

            return $created;
        }, 3);

        return $this->buildResponse($subjectEnrollment, $idempotencyKey, false);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->description('Internal ID of the student enrollment (optional if student_id is provided).'),
            'student_id' => $schema->string()->description('Student ID or student number to resolve their active enrollment record.'),
            'subject_id' => $schema->integer()->min(1)->description('Subject ID to enroll in (optional if subject_code is provided).'),
            'subject_code' => $schema->string()->description('Subject code to enroll in (e.g. CS101, GE 1).'),
            'class_id' => $schema->integer()->min(1)->description('Optional scheduled class section ID.'),
            'section' => $schema->string()->max(50)->description('Optional section name override.'),
            'subjects' => $schema->array()->description('List of subjects for batch enrollment.')->items(
                $schema->object(fn ($s) => [
                    'subject_id' => $s->integer()->description('Subject ID.'),
                    'subject_code' => $s->string()->description('Subject code.'),
                    'class_id' => $s->integer()->description('Class ID.'),
                    'section' => $s->string()->description('Section.'),
                ])
            ),
            'idempotency_key' => $schema->string()->min(1)->max(96)->description('Unique idempotency key for safe retries.'),
        ];
    }

    private function buildResponse(SubjectEnrollment $se, string $idempotencyKey, bool $replayed): ResponseFactory
    {
        $se->loadMissing('subject');

        return Response::structured([
            'id' => $se->id,
            'enrollment_id' => $se->enrollment_id,
            'student_id' => $se->student_id,
            'subject' => [
                'id' => $se->subject_id,
                'code' => $se->subject?->code ?? $se->external_subject_code,
                'title' => $se->subject?->title ?? $se->external_subject_title,
                'units' => $se->subject?->units ?? 0,
            ],
            'class_id' => $se->class_id,
            'section' => $se->section,
            'school_year' => $se->school_year,
            'semester' => $se->semester,
            'replayed' => $replayed,
            'idempotency_key' => $idempotencyKey,
        ]);
    }

    private function resolveStudent(string $identifier): ?\App\Models\Student
    {
        $school = $this->school();

        return \App\Models\Student::query()
            ->where(fn ($q) => $q->where('school_id', $school->id)->orWhere('institution_id', $school->id))
            ->where(function ($query) use ($identifier) {
                if (is_numeric($identifier)) {
                    $query->where('id', (int) $identifier)
                        ->orWhere('student_id', (int) $identifier);
                }
                $query->orWhere('student_id', $identifier)
                    ->orWhere('email', $identifier);
            })
            ->first();
    }
}
