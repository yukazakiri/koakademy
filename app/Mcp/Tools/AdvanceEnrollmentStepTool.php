<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\StudentEnrollment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Advance an enrollment to its next workflow step or execute an authorized verification transition. Requires MCP write access, administrator access, Update:StudentEnrollment permission, and an idempotency key.')]
#[IsIdempotent]
final class AdvanceEnrollmentStepTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?EnrollmentWorkflowCoordinator $coordinator = null)
    {
        $this->coordinator ??= app(EnrollmentWorkflowCoordinator::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireAdminWrite($request);
        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to advance enrollment workflow steps.');

        $validated = $request->validate([
            'enrollment_id' => ['required', 'integer', 'min:1'],
            'transition_key' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:96'],
        ], [
            'idempotency_key.required' => 'An idempotency key is required so this transition can be safely retried.',
        ]);

        $enrollment = StudentEnrollment::query()->findOrFail($validated['enrollment_id']);

        if (! $enrollment->belongsToCurrentSchool()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The enrollment record does not belong to the selected school.');
        }

        $payload = [];
        if (! empty($validated['reason'])) {
            $payload['reason'] = $validated['reason'];
        }

        $result = $this->coordinator->transition(
            $enrollment,
            $user,
            $validated['transition_key'] ?? null,
            $payload,
            $validated['idempotency_key'],
        );

        $enrollment->refresh();

        return Response::structured([
            'enrollment_id' => $enrollment->id,
            'successful' => $result->successful,
            'from_step' => $result->fromStepKey,
            'to_step' => $result->toStepKey,
            'status' => $enrollment->status,
            'current_step_key' => $enrollment->current_step_key,
            'message' => $result->message,
            'idempotency_key' => $validated['idempotency_key'],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->required()->description('The internal enrollment ID to advance.'),
            'transition_key' => $schema->string()->max(100)->description('Optional target transition key for policy-based workflows. Omit to advance along the default path.'),
            'reason' => $schema->string()->max(1000)->description('Optional audit note or reason for this transition.'),
            'idempotency_key' => $schema->string()->min(1)->max(96)->required()->description('A unique key to guarantee safe replay and prevent duplicate transitions.'),
        ];
    }
}
