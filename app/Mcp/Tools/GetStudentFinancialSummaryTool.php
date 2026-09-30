<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Services\Ai\InstitutionEntityResolver;
use App\Services\Ai\StudentFinancialSummaryService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Student-scoped entry point for tuition, balance, and payment questions.
 *
 * GetStatementOfAccountTool is enrollment-scoped, which forces the caller to
 * resolve a student, then their enrollment, then the enrollment ID before it can
 * read a single peso. Administrators think in terms of students ("what does
 * Maria owe?"), so this tool accepts a student identifier directly and returns
 * every assessment term plus the payment history in one call.
 */
#[Description('Get a student\'s real tuition assessment, outstanding balance, and payment history. Accepts a student number, name, or email. All figures are read from the official billing ledger, never estimated.')]
#[IsReadOnly]
final class GetStudentFinancialSummaryTool extends Tool
{
    use AuthorizesMcpRequests;

    public function __construct(
        private ?StudentFinancialSummaryService $summary = null,
        private ?InstitutionEntityResolver $entities = null,
    ) {
        $this->summary ??= app(StudentFinancialSummaryService::class);
        $this->entities ??= app(InstitutionEntityResolver::class);
    }

    public function handle(Request $request): ResponseFactory
    {
        $user = $this->requireRead($request);
        $this->authorizeFinanceAccess($user);

        $validated = $request->validate([
            'student' => ['nullable', 'string', 'max:120'],
            'student_id' => ['nullable'],
            'school_year' => ['nullable', 'string', 'max:20'],
            'semester' => ['nullable', 'integer', 'in:1,2'],
            'include_payments' => ['nullable', 'boolean'],
            'payment_limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $identifier = $validated['student'] ?? $validated['student_id'] ?? null;

        if (blank($identifier)) {
            throw new \Illuminate\Validation\ValidationException(
                \Illuminate\Support\Facades\Validator::make([], ['student' => 'A student number, name, or email is required.']),
            );
        }

        $student = $this->entities->student($identifier, $this->school());
        $this->authorizeStudentAccess($user, $student);

        $summary = $this->summary->summarize(
            student: $student,
            schoolYear: $validated['school_year'] ?? null,
            semester: isset($validated['semester']) ? (int) $validated['semester'] : null,
            includePayments: (bool) ($validated['include_payments'] ?? true),
            paymentLimit: (int) ($validated['payment_limit'] ?? 10),
        );

        return Response::structured($summary);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'student' => $schema->string()->max(120)->description('Student number, full name, or email (e.g. "2024-0012", "Maria Santos").'),
            'student_id' => $schema->string()->description('Alias for "student"; accepts the internal student record ID as well.'),
            'school_year' => $schema->string()->max(20)->description('Optional school year filter (e.g. 2026-2027). Omit to return every term on record.'),
            'semester' => $schema->integer()->enum([1, 2])->description('Optional semester filter (1 or 2).'),
            'include_payments' => $schema->boolean()->description('Include the payment history list. Default true.'),
            'payment_limit' => $schema->integer()->min(1)->max(50)->description('Maximum payment records to return. Default 10.'),
        ];
    }

    private function authorizeFinanceAccess(\App\Models\User $user): void
    {
        if ($user->can('View:Cashier') || $user->can('view_tuition_fees') || $user->hasRole('super_admin')) {
            return;
        }

        throw new \Illuminate\Auth\Access\AuthorizationException('You are not permitted to view tuition, balances, or payment records.');
    }

    private function authorizeStudentAccess(\App\Models\User $user, \App\Models\Student $student): void
    {
        if ($user->isStudentRole() && (int) $student->id !== (int) app(\App\Services\ApiIdentityService::class)->studentFor($user)?->id) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Students can only access their own financial records.');
        }
    }
}
