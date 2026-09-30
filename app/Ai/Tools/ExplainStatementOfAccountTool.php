<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Services\Ai\InstitutionEntityResolver;
use App\Services\Ai\StudentFinancialSummaryService;
use App\Services\TenantContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reads the official billing ledger and renders it for a bursar-facing answer.
 *
 * This tool previously returned hardcoded figures (28500.00 assessed, 15000.00
 * paid, a fabricated "Online Bank Transfer" method) for every student, which
 * meant any balance the administrator was shown was invented. It now resolves
 * the student, then delegates every peso to EnrollmentBillingService via
 * StudentFinancialSummaryService.
 */
final class ExplainStatementOfAccountTool implements Tool
{
    public function __construct(
        private ?StudentFinancialSummaryService $summary = null,
        private ?InstitutionEntityResolver $entities = null,
    ) {
        $this->summary ??= app(StudentFinancialSummaryService::class);
        $this->entities ??= app(InstitutionEntityResolver::class);
    }

    public function description(): Stringable|string
    {
        return 'Look up a student\'s real tuition assessment, outstanding balance, and payment history, then explain the Statement of Account line items. Accepts a student number, name, or email — never guess or estimate amounts.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'student' => 'nullable|string|max:120',
            'student_id' => 'nullable',
            'school_year' => 'nullable|string|max:20',
            'semester' => 'nullable|integer|in:1,2',
            'include_payments' => 'nullable|boolean',
        ]);

        $identifier = $validated['student'] ?? $validated['student_id'] ?? null;

        if (blank($identifier)) {
            return json_encode([
                'error' => true,
                'message' => 'A student number, name, or email is required. Use SearchStudentsTool to resolve the student first if you only have a partial name.',
            ], JSON_PRETTY_PRINT);
        }

        try {
            $student = $this->entities->student($identifier, $this->currentSchool());
        } catch (\App\Services\Ai\Exceptions\EntityNotFoundException|\App\Services\Ai\Exceptions\AmbiguousEntityException $e) {
            return json_encode(['error' => true, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }

        $summary = $this->summary->summarize(
            student: $student,
            schoolYear: $validated['school_year'] ?? null,
            semester: isset($validated['semester']) ? (int) $validated['semester'] : null,
            includePayments: (bool) ($validated['include_payments'] ?? true),
        );

        $summary['source'] = 'official_billing_ledger';
        $summary['note'] = 'Every amount is read from the enrollment billing ledger. Do not restate a figure that is not present here.';

        return json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'student' => $schema->string()->max(120)->description('Student number, full name, or email (e.g. "2024-0012", "Maria Santos").'),
            'student_id' => $schema->string()->description('Alias for "student". Accepts the internal student record ID too.'),
            'school_year' => $schema->string()->max(20)->description('Optional school year filter (e.g. 2026-2027). Omit for every term on record.'),
            'semester' => $schema->integer()->enum([1, 2])->description('Optional semester filter (1 or 2).'),
            'include_payments' => $schema->boolean()->description('Include the payment history list. Default true.'),
        ];
    }

    private function currentSchool(): ?\App\Models\School
    {
        $school = app(TenantContext::class)->getCurrentSchool();

        return $school instanceof \App\Models\School ? $school : null;
    }
}
