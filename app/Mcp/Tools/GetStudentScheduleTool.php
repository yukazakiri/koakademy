<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Student;
use App\Services\StudentScheduleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Get the current schedule and detected schedule conflicts for a student in the selected school.')]
#[IsReadOnly]
final class GetStudentScheduleTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(private ?StudentScheduleService $schedules = null)
    {
        $this->schedules ??= app(StudentScheduleService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'View:Student', 'You are not permitted to view student schedules.');
        $validated = $request->validate(['student_id' => ['required', 'integer', 'min:1']]);

        $student = Student::query()
            ->select(['id', 'student_id', 'first_name', 'middle_name', 'last_name', 'suffix', 'school_id'])
            ->findOrFail($validated['student_id']);

        $schedule = $this->schedules->build($student);

        return Response::structured([
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
            ],
            ...$schedule,
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->min(1)->required()->description('The internal student ID returned by search_students.'),
        ];
    }
}
