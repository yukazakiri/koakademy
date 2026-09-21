<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

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

#[Description('Update administrative remarks or registrar notes on an enrollment record. Requires MCP write access and Update:StudentEnrollment permission.')]
#[IsIdempotent]
final class UpdateEnrollmentRemarksTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:StudentEnrollment', 'You are not permitted to update enrollment remarks.');

        $validated = $request->validate([
            'enrollment_id' => ['required', 'integer', 'min:1'],
            'remarks' => ['required', 'string', 'max:2000'],
        ]);

        $enrollment = StudentEnrollment::query()->findOrFail($validated['enrollment_id']);

        if (! $enrollment->belongsToCurrentSchool()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('The enrollment record does not belong to the selected school.');
        }

        $enrollment->forceFill(['remarks' => mb_trim((string) $validated['remarks'])])->save();

        return Response::structured([
            'enrollment_id' => $enrollment->id,
            'remarks' => $enrollment->remarks,
            'updated_at' => $enrollment->updated_at?->toIso8601String(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->required()->description('The internal enrollment ID.'),
            'remarks' => $schema->string()->max(2000)->required()->description('Administrative remarks or registrar notes to record.'),
        ];
    }
}
