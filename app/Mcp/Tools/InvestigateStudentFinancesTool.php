<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Student;
use App\Services\StudentInvestigationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Investigate a student\'s tuition assessments, ledgers, and transactions to detect financial calculation mistakes, balance desynchronization, duplicate payments, or unallocated transactions.')]
#[IsReadOnly]
final class InvestigateStudentFinancesTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?StudentInvestigationService $investigationService = null,
    ) {
        $this->investigationService ??= app(StudentInvestigationService::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->requirePermission($user, 'view_tuition_fees', 'You are not permitted to audit financial ledgers.');

        $studentId = $request->get('student_id');
        if (blank($studentId)) {
            return Response::structured(['error' => true, 'message' => 'student_id is required for financial investigation.']);
        }

        $student = $this->resolveStudent((string) $studentId, withTrashed: true);
        if (! $student instanceof Student) {
            return Response::structured(['error' => true, 'message' => "Student '{$studentId}' not found."]);
        }

        $schoolYear = $request->get('school_year') ? (string) $request->get('school_year') : null;
        $semester = $request->get('semester') ? (int) $request->get('semester') : null;

        $report = $this->investigationService->investigateFinances($student, $schoolYear, $semester);

        return Response::structured($report);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'student_id' => $schema->string()
                ->required()
                ->description('Student database ID or student number to audit.'),
            'school_year' => $schema->string()
                ->description('Optional school year (e.g. "2025 - 2026") to limit scope.'),
            'semester' => $schema->integer()
                ->enum([1, 2])
                ->description('Optional semester (1 or 2) to limit scope.'),
        ];
    }

    private function resolveStudent(string $identifier, bool $withTrashed = false): ?Student
    {
        $query = $withTrashed ? Student::withTrashed() : Student::query();

        if (is_numeric($identifier)) {
            $found = (clone $query)->find((int) $identifier);
            if ($found) {
                return $found;
            }
        }

        return $query->where('student_id', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }
}
