<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\ClassEnrollment;
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

#[Description('Record or update official grades (prelim, midterm, finals, or overall grade) and remarks on an enrolled subject. Requires MCP write access, Update:StudentEnrollment permission, and an idempotency key.')]
#[IsIdempotent]
final class UpdateSubjectEnrollmentGradeTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to update student subject grades.');

        $validated = $request->validate([
            'subject_enrollment_id' => ['required', 'integer', 'min:1'],
            'grade' => ['nullable', 'numeric', 'between:0,100'],
            'prelim_grade' => ['nullable', 'numeric', 'between:0,100'],
            'midterm_grade' => ['nullable', 'numeric', 'between:0,100'],
            'finals_grade' => ['nullable', 'numeric', 'between:0,100'],
            'remarks' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:96'],
        ], [
            'idempotency_key.required' => 'An idempotency key is required so this grade update can be safely retried.',
        ]);

        $subjectEnrollment = SubjectEnrollment::query()
            ->with(['enrollment', 'subject'])
            ->findOrFail($validated['subject_enrollment_id']);

        if (! $subjectEnrollment->belongsToCurrentSchool() && ($subjectEnrollment->enrollment && ! $subjectEnrollment->enrollment->belongsToCurrentSchool())) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The subject enrollment record does not belong to the selected school.');
        }

        $scopedKey = hash('sha256', "mcp:update-grade:{$subjectEnrollment->id}:{$validated['idempotency_key']}");
        $existingEvent = EnrollmentWorkflowEvent::query()->where('idempotency_key', $scopedKey)->first();

        if ($existingEvent instanceof EnrollmentWorkflowEvent) {
            return $this->buildResponse($subjectEnrollment->refresh(), $validated['idempotency_key'], true);
        }

        DB::transaction(function () use ($subjectEnrollment, $validated, $scopedKey, $user): void {
            if (array_key_exists('grade', $validated) && $validated['grade'] !== null) {
                $subjectEnrollment->grade = (float) $validated['grade'];
            }

            if (array_key_exists('remarks', $validated)) {
                $subjectEnrollment->remarks = $validated['remarks'] !== null ? mb_trim((string) $validated['remarks']) : null;
            }

            $subjectEnrollment->save();

            // Synchronize class enrollment grades if linked to a scheduled class
            if ($subjectEnrollment->class_id && $subjectEnrollment->student_id) {
                $classEnrollment = ClassEnrollment::query()
                    ->where('student_id', $subjectEnrollment->student_id)
                    ->where('class_id', $subjectEnrollment->class_id)
                    ->first();

                if ($classEnrollment instanceof ClassEnrollment) {
                    $dirty = false;

                    if (isset($validated['prelim_grade'])) {
                        $classEnrollment->prelim_grade = (float) $validated['prelim_grade'];
                        $dirty = true;
                    }

                    if (isset($validated['midterm_grade'])) {
                        $classEnrollment->midterm_grade = (float) $validated['midterm_grade'];
                        $dirty = true;
                    }

                    if (isset($validated['finals_grade'])) {
                        $classEnrollment->finals_grade = (float) $validated['finals_grade'];
                        $dirty = true;
                    }

                    if (isset($validated['grade'])) {
                        $classEnrollment->total_average = (float) $validated['grade'];
                        $dirty = true;
                    }

                    if ($dirty) {
                        $classEnrollment->save();
                    }
                }
            }

            EnrollmentWorkflowEvent::query()->create([
                'student_enrollment_id' => $subjectEnrollment->enrollment_id ?? 0,
                'actor_id' => $user->id,
                'event_type' => 'subject_grade_updated',
                'idempotency_key' => $scopedKey,
                'result' => [
                    'subject_enrollment_id' => $subjectEnrollment->id,
                    'grade' => $subjectEnrollment->grade,
                    'remarks' => $subjectEnrollment->remarks,
                ],
            ]);
        }, 3);

        return $this->buildResponse($subjectEnrollment->refresh(), $validated['idempotency_key'], false);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'subject_enrollment_id' => $schema->integer()->min(1)->required()->description('The ID of the subject_enrollment record.'),
            'grade' => $schema->number()->min(0)->max(100)->description('Official grade score (e.g. 1.0 - 5.0 or percentage score like 85.5).'),
            'prelim_grade' => $schema->number()->min(0)->max(100)->description('Optional prelim examination/term grade score.'),
            'midterm_grade' => $schema->number()->min(0)->max(100)->description('Optional midterm examination/term grade score.'),
            'finals_grade' => $schema->number()->min(0)->max(100)->description('Optional final examination/term grade score.'),
            'remarks' => $schema->string()->max(255)->description('Evaluation remarks (e.g. "Passed", "Failed", "Incomplete", "Dropped").'),
            'idempotency_key' => $schema->string()->min(1)->max(96)->required()->description('A unique key to guarantee safe replay.'),
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
            ],
            'grade' => $se->grade,
            'remarks' => $se->remarks,
            'replayed' => $replayed,
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}
