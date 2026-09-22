<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the historical audit trail of workflow events, transitions, and requirement reviews for an enrollment.')]
#[IsReadOnly]
final class GetEnrollmentAuditTrailTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view enrollment audit trails.');

        $validated = $request->validate([
            'enrollment_id' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $enrollment = StudentEnrollment::query()->findOrFail($validated['enrollment_id']);

        if (! $enrollment->belongsToCurrentSchool()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The enrollment record does not belong to the selected school.');
        }

        $limit = (int) ($validated['limit'] ?? 25);

        $events = EnrollmentWorkflowEvent::query()
            ->where('student_enrollment_id', $enrollment->id)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (EnrollmentWorkflowEvent $event): array {
                $actor = $event->actor_id ? User::query()->find($event->actor_id) : null;

                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'from_step_key' => $event->from_step_key,
                    'to_step_key' => $event->to_step_key,
                    'status' => $event->status,
                    'terminal_outcome' => $event->terminal_outcome,
                    'reason' => $event->reason,
                    'result' => $event->result,
                    'actor' => $actor === null ? null : [
                        'id' => $actor->id,
                        'name' => $actor->name,
                        'role' => $actor->role?->value,
                    ],
                    'created_at' => $event->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return Response::structured([
            'enrollment_id' => $enrollment->id,
            'count' => count($events),
            'events' => $events,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->required()->description('The internal enrollment ID.'),
            'limit' => $schema->integer()->min(1)->max(50)->description('Maximum events to retrieve. Defaults to 25.'),
        ];
    }
}
