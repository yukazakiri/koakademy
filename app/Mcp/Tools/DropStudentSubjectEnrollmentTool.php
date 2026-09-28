<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\SubjectEnrollment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Drop or remove a subject enrollment from a student\'s record, releasing any linked class seat. Requires MCP write access, Update:StudentEnrollment permission, and an idempotency key.')]
#[IsIdempotent]
final class DropStudentSubjectEnrollmentTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to drop student subject enrollments.');

        $validated = $request->validate([
            'subject_enrollment_id' => ['nullable', 'integer', 'min:1'],
            'student_id' => ['nullable'],
            'subject_code' => ['nullable', 'string'],
            'subject_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'min:1', 'max:96'],
        ], [
            'reason.required' => 'An explanatory reason is required to drop an enrolled subject.',
        ]);

        $idempotencyKey = $validated['idempotency_key'] ?? (string) \Illuminate\Support\Str::uuid();

        // When the caller references a record by ID, check the idempotency
        // log first so replaying a drop after the row was deleted still
        // returns the original result instead of "record not found".
        if (filled($validated['subject_enrollment_id'] ?? null)) {
            $providedId = (int) $validated['subject_enrollment_id'];
            $replayKey = hash('sha256', "mcp:drop-subject:{$providedId}:{$idempotencyKey}");
            $replayedEvent = EnrollmentWorkflowEvent::query()->where('idempotency_key', $replayKey)->first();
            if ($replayedEvent instanceof EnrollmentWorkflowEvent) {
                return Response::structured([
                    'subject_enrollment_id' => $providedId,
                    'dropped' => true,
                    'replayed' => true,
                    'reason' => $replayedEvent->reason,
                    'idempotency_key' => $idempotencyKey,
                    'message' => 'This subject enrollment was already dropped.',
                ]);
            }

            $subjectEnrollment = SubjectEnrollment::query()->with(['enrollment', 'subject'])->find($providedId);
        } elseif (filled($validated['student_id'] ?? null)) {
            $subjectEnrollment = null;
            $student = $this->resolveStudent((string) $validated['student_id']);
            if ($student instanceof \App\Models\Student) {
                $query = SubjectEnrollment::query()->where('student_id', $student->id)->with(['enrollment', 'subject'])->latest('id');
                if (filled($validated['subject_id'] ?? null)) {
                    $query->where('subject_id', (int) $validated['subject_id']);
                } elseif (filled($validated['subject_code'] ?? null)) {
                    $code = mb_strtoupper(mb_trim((string) $validated['subject_code']));
                    $query->whereHas('subject', fn ($q) => $q->where('code', $code));
                }
                $subjectEnrollment = $query->first();
            }
        }

        if (! $subjectEnrollment instanceof SubjectEnrollment) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'subject_enrollment_id' => 'Subject enrollment record not found. Please provide a valid subject_enrollment_id or student_id and subject_code.',
            ]);
        }

        $subjectEnrollmentId = (int) $subjectEnrollment->id;
        $scopedKey = hash('sha256', "mcp:drop-subject:{$subjectEnrollmentId}:{$idempotencyKey}");

        $existingEvent = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedKey)->first();
        if ($existingEvent instanceof EnrollmentWorkflowEvent) {
            return Response::structured([
                'subject_enrollment_id' => $subjectEnrollmentId,
                'dropped' => true,
                'replayed' => true,
                'reason' => $existingEvent->reason,
                'idempotency_key' => $idempotencyKey,
                'message' => 'This subject enrollment was already dropped.',
            ]);
        }

        if (! $subjectEnrollment->belongsToCurrentSchool() && ($subjectEnrollment->enrollment && ! $subjectEnrollment->enrollment->belongsToCurrentSchool())) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The subject enrollment record does not belong to the selected school.');
        }

        $meta = [
            'id' => $subjectEnrollment->id,
            'enrollment_id' => $subjectEnrollment->enrollment_id,
            'student_id' => $subjectEnrollment->student_id,
            'subject_id' => $subjectEnrollment->subject_id,
            'subject_code' => $subjectEnrollment->subject?->code ?? $subjectEnrollment->external_subject_code ?? 'N/A',
            'subject_title' => $subjectEnrollment->subject?->title ?? $subjectEnrollment->external_subject_title ?? 'N/A',
        ];

        $enrollment = $subjectEnrollment->enrollment;

        DB::transaction(function () use ($subjectEnrollment, $enrollment, $meta, $validated, $scopedKey, $user): void {
            $subjectEnrollment->delete();

            if ($enrollment instanceof \App\Models\StudentEnrollment && $enrollment->studentTuition()->exists()) {
                app(\App\Services\EnrollmentBillingService::class)->recalculateEnrollmentTuition($enrollment);
            }

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $meta['enrollment_id'] ?? 0,
                'actor_id' => $user->id,
                'event_type' => 'subject_dropped',
                'idempotency_key' => $scopedKey,
                'reason' => mb_trim((string) $validated['reason']),
                'result' => $meta,
            ]);
        }, 3);

        return Response::structured([
            'subject_enrollment_id' => $subjectEnrollmentId,
            'dropped' => true,
            'replayed' => false,
            'subject' => [
                'code' => $meta['subject_code'],
                'title' => $meta['subject_title'],
            ],
            'reason' => mb_trim((string) $validated['reason']),
            'idempotency_key' => $idempotencyKey,
            'message' => 'Subject successfully dropped.',
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject_enrollment_id' => $schema->integer()->min(1)->description('The ID of the subject_enrollment record to drop.'),
            'student_id' => $schema->string()->description('Student ID or student number to find the subject enrollment.'),
            'subject_code' => $schema->string()->description('Subject code to drop (e.g. CS101).'),
            'reason' => $schema->string()->min(3)->max(1000)->required()->description('The documented reason for dropping this subject.'),
            'idempotency_key' => $schema->string()->min(1)->max(96)->description('A unique key to guarantee safe replay.'),
        ];
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
