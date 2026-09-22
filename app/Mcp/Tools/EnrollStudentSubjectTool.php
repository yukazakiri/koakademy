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
use InvalidArgumentException;
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
            'enrollment_id' => ['required', 'integer', 'min:1'],
            'subject_id' => ['required', 'integer', 'min:1'],
            'class_id' => ['nullable', 'integer', 'min:1'],
            'section' => ['nullable', 'string', 'max:50'],
            'is_modular' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:96'],
        ], [
            'idempotency_key.required' => 'An idempotency key is required so this subject enrollment can be safely retried.',
        ]);

        $enrollment = StudentEnrollment::query()->findOrFail($validated['enrollment_id']);

        if (! $enrollment->belongsToCurrentSchool()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The enrollment record does not belong to the selected school.');
        }

        $subject = Subject::query()->findOrFail($validated['subject_id']);

        if ($enrollment->course_id !== null && (int) $subject->course_id !== (int) $enrollment->course_id) {
            throw new \Illuminate\Auth\Access\AuthorizationException("Subject [{$subject->code}] does not belong to the enrollment program.");
        }

        $scopedKey = hash('sha256', "mcp:enroll-subject:{$enrollment->id}:{$subject->id}:{$validated['idempotency_key']}");

        $existingEvent = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedKey)->first();
        if ($existingEvent instanceof EnrollmentWorkflowEvent) {
            $existingRecord = SubjectEnrollment::query()->find($existingEvent->result['subject_enrollment_id'] ?? null);
            if ($existingRecord instanceof SubjectEnrollment) {
                return $this->buildResponse($existingRecord, $validated['idempotency_key'], true);
            }
        }

        // Avoid duplicate subject enrollment in the same term
        $existing = SubjectEnrollment::query()
            ->where('enrollment_id', $enrollment->id)
            ->where('subject_id', $subject->id)
            ->first();

        if ($existing instanceof SubjectEnrollment) {
            return $this->buildResponse($existing, $validated['idempotency_key'], true);
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
                throw new InvalidArgumentException("Class section [{$class->section}] is not scheduled for school year [{$enrollment->school_year}], semester [{$enrollment->semester}].");
            }

            $classCode = mb_trim((string) $class->subject_code);
            $subjectCode = mb_trim((string) $subject->code);
            $subjectMatches = (int) $class->subject_id === (int) $subject->id
                || ($classCode !== '' && $subjectCode !== '' && $classCode === $subjectCode)
                || in_array((int) $subject->id, array_map(intval(...), $class->subject_ids ?? []), true);

            if (! $subjectMatches) {
                throw new InvalidArgumentException("Class section [{$class->section}] ({$class->subject_code}) does not match subject [{$subject->code}].");
            }

            $enrolledCount = $class->class_enrollments()->where('status', true)->count();
            $alreadyEnrolledInClass = \App\Models\ClassEnrollment::query()
                ->where('class_id', $class->id)
                ->where('student_id', $enrollment->student_id)
                ->exists();

            if (! $alreadyEnrolledInClass && (int) $class->maximum_slots > 0 && $enrolledCount >= (int) $class->maximum_slots) {
                throw new InvalidArgumentException("Class section [{$class->section}] has no available seats ({$enrolledCount}/{$class->maximum_slots}).");
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

        return $this->buildResponse($subjectEnrollment, $validated['idempotency_key'], false);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->required()->description('The internal ID of the student enrollment.'),
            'subject_id' => $schema->integer()->min(1)->required()->description('The internal ID of the curriculum subject to enroll in.'),
            'class_id' => $schema->integer()->min(1)->description('Optional scheduled class ID to assign student to a specific schedule and section.'),
            'section' => $schema->string()->max(50)->description('Optional section name override.'),
            'is_modular' => $schema->boolean()->description('Optional modular learning flag. Defaults to false.'),
            'idempotency_key' => $schema->string()->min(1)->max(96)->required()->description('A unique key to guarantee safe replay and prevent duplicate subject additions.'),
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
}
