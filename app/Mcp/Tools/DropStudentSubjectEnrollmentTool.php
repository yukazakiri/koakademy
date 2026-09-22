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
            'subject_enrollment_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:96'],
        ], [
            'reason.required' => 'An explanatory reason is required to drop an enrolled subject.',
            'idempotency_key.required' => 'An idempotency key is required so this subject drop can be safely retried.',
        ]);

        $subjectEnrollmentId = (int) $validated['subject_enrollment_id'];
        $scopedKey = hash('sha256', "mcp:drop-subject:{$subjectEnrollmentId}:{$validated['idempotency_key']}");

        $existingEvent = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedKey)->first();
        if ($existingEvent instanceof EnrollmentWorkflowEvent) {
            return Response::structured([
                'subject_enrollment_id' => $subjectEnrollmentId,
                'dropped' => true,
                'replayed' => true,
                'reason' => $existingEvent->reason,
                'idempotency_key' => $validated['idempotency_key'],
                'message' => 'This subject enrollment was already dropped.',
            ]);
        }

        $subjectEnrollment = SubjectEnrollment::query()
            ->with(['enrollment', 'subject'])
            ->findOrFail($subjectEnrollmentId);

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
            'idempotency_key' => $validated['idempotency_key'],
            'message' => 'Subject successfully dropped.',
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject_enrollment_id' => $schema->integer()->min(1)->required()->description('The ID of the subject_enrollment record to drop.'),
            'reason' => $schema->string()->min(3)->max(1000)->required()->description('The documented reason for dropping this subject.'),
            'idempotency_key' => $schema->string()->min(1)->max(96)->required()->description('A unique key to guarantee safe replay.'),
        ];
    }
}
