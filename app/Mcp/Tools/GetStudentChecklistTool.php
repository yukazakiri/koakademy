<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Services\StudentChecklistService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Investigate and analyze a student\'s complete curriculum checklist, grades, earned units, GWA, remaining deficiencies, failed retakes, and prerequisite blockers.')]
#[IsReadOnly]
final class GetStudentChecklistTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?StudentChecklistService $checklistService = null,
    ) {
        $this->checklistService ??= app(StudentChecklistService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);

        $studentId = $request->get('student_id') !== null ? (int) $request->get('student_id') : null;
        $student = $this->resolveStudentForCaller($user, $studentId);

        if (! $user->isStudentRole()) {
            $this->requirePermission($user, 'View:Student', 'You are not permitted to view student curriculum checklists.');
        }

        $options = [
            'year_level' => $request->get('year_level') ? (int) $request->get('year_level') : null,
            'semester' => $request->get('semester') ? (int) $request->get('semester') : null,
            'status' => $request->get('status') ? (string) $request->get('status') : null,
        ];

        $analysis = $this->checklistService->analyze($student, $options);

        return Response::structured($analysis);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->integer()->min(1)->description('The student database ID or student number. Optional for student callers.'),
            'year_level' => $schema->integer()->min(1)->max(6)->description('Optional filter by curriculum year level (1 to 5).'),
            'semester' => $schema->integer()->enum([1, 2])->description('Optional filter by semester (1 or 2).'),
            'status' => $schema->string()->enum(['all', 'completed', 'in_progress', 'failed', 'deficient'])->description('Optional filter by subject checklist status.'),
        ];
    }
}
