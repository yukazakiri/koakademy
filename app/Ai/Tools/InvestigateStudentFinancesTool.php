<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Student;
use App\Models\User;
use App\Services\StudentInvestigationService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class InvestigateStudentFinancesTool implements Tool
{
    public function __construct(
        private ?StudentInvestigationService $investigationService = null,
    ) {
        $this->investigationService ??= app(StudentInvestigationService::class);
    }

    public function description(): Stringable|string
    {
        return 'Audit a student\'s tuition assessments, ledgers, and transactions to detect financial calculation mistakes, balance desynchronization, duplicate payments, or unallocated transactions.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return json_encode(['error' => true, 'message' => 'Authentication is required.']);
        }

        if (! $user->hasRole('super_admin') && ! $user->can('view_tuition_fees') && ! $user->can('View:Cashier')) {
            return json_encode(['error' => true, 'message' => 'You are not permitted to audit financial ledgers.']);
        }

        $studentId = $request['student_id'] ?? null;
        if (! $studentId) {
            return json_encode(['error' => true, 'message' => 'student_id is required for financial investigation.']);
        }

        $student = $this->resolveStudent((string) $studentId, withTrashed: true);
        if (! $student instanceof Student) {
            return json_encode(['error' => true, 'message' => "Student '{$studentId}' not found."]);
        }

        $schoolYear = isset($request['school_year']) ? (string) $request['school_year'] : null;
        $semester = isset($request['semester']) ? (int) $request['semester'] : null;

        $report = $this->investigationService->investigateFinances($student, $schoolYear, $semester);

        return json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

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
