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
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get an enrollment record, its workflow state, and enrollment requirements for the selected school.')]
#[IsReadOnly]
final class GetEnrollmentStatusTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'View:StudentEnrollment', 'You are not permitted to view enrollment records.');
        $validated = $request->validate(['enrollment_id' => ['required', 'integer', 'min:1']]);

        $enrollment = StudentEnrollment::query()
            ->with([
                'student:id,student_id,first_name,middle_name,last_name,suffix',
                'course:id,code,title',
                'requirements:id,student_enrollment_id,requirement_key,label,description,is_required,status,enforcement_step_key,verified_at,waived_at,waiver_reason',
            ])
            ->findOrFail($validated['enrollment_id']);

        return Response::structured([
            'id' => $enrollment->id,
            'status' => $enrollment->status,
            'workflow_runtime' => $enrollment->workflow_runtime,
            'current_step_key' => $enrollment->current_step_key,
            'terminal_outcome' => $enrollment->terminal_outcome,
            'academic_period' => [
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'academic_year' => $enrollment->academic_year,
            ],
            'student' => $enrollment->student === null ? null : [
                'id' => $enrollment->student->id,
                'student_number' => (string) $enrollment->student->student_id,
                'name' => $enrollment->student->full_name,
            ],
            'course' => $enrollment->course === null ? null : [
                'id' => $enrollment->course->id,
                'code' => $enrollment->course->code,
                'title' => $enrollment->course->title,
            ],
            'requirements' => $enrollment->requirements
                ->map(fn ($requirement): array => [
                    'id' => $requirement->id,
                    'key' => $requirement->requirement_key,
                    'label' => $requirement->label,
                    'description' => $requirement->description,
                    'required' => $requirement->is_required,
                    'status' => $requirement->status,
                    'enforcement_step_key' => $requirement->enforcement_step_key,
                    'verified_at' => $requirement->verified_at?->toIso8601String(),
                    'waived_at' => $requirement->waived_at?->toIso8601String(),
                    'waiver_reason' => $requirement->waiver_reason,
                ])
                ->values()
                ->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'enrollment_id' => $schema->integer()->min(1)->required()->description('The internal enrollment ID.'),
        ];
    }
}
