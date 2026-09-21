<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\EnrollmentRequirement;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Mark one pending enrollment requirement as verified. This writes data, requires MCP write access and enrollment-update permission, and needs an idempotency key.')]
#[IsIdempotent]
final class VerifyEnrollmentRequirementTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?EnrollmentWorkflowCoordinator $enrollments = null)
    {
        $this->enrollments ??= app(EnrollmentWorkflowCoordinator::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to verify enrollment requirements.');
        $validated = $request->validate([
            'requirement_id' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'min:1', 'max:96'],
        ], [
            'idempotency_key.required' => 'An idempotency key is required so this verification can be safely retried.',
        ]);

        $requirement = EnrollmentRequirement::query()
            ->with('enrollment:id,school_id')
            ->findOrFail($validated['requirement_id']);

        if ($requirement->enrollment === null || ! $requirement->enrollment->belongsToCurrentSchool()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The enrollment requirement is not available in the selected school.');
        }

        if ($requirement->status === EnrollmentRequirement::Waived) {
            return Response::error('A waived enrollment requirement cannot be verified through this tool.');
        }

        $updated = $this->enrollments->verifyRequirement(
            $requirement,
            $user,
            idempotencyKey: $validated['idempotency_key'],
        );

        return Response::structured([
            'id' => $updated->id,
            'enrollment_id' => $updated->student_enrollment_id,
            'key' => $updated->requirement_key,
            'label' => $updated->label,
            'status' => $updated->status,
            'verified_at' => $updated->verified_at?->toIso8601String(),
            'verified_by' => $updated->verified_by,
            'idempotency_key' => $validated['idempotency_key'],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'requirement_id' => $schema->integer()->min(1)->required()->description('The internal enrollment requirement ID returned by get_enrollment_status.'),
            'idempotency_key' => $schema->string()->min(1)->max(96)->required()->description('A unique, stable key for this intended verification. Reuse it only to retry the same request.'),
        ];
    }
}
