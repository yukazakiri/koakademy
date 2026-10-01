<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Ai\Exceptions\AmbiguousEntityException;
use App\Services\Ai\Exceptions\EntityNotFoundException;
use App\Services\Ai\InstitutionEntityResolver;
use App\Services\Ai\StudentFinancialSummaryService;
use App\Services\ApiIdentityService;
use App\Services\TenantContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
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

        // This tool is reachable from BursarFinanceAgent, which students can
        // use. Without a gate, a student could name any classmate and read
        // their real assessments and payments.
        $user = Auth::user();

        if (! $user instanceof User) {
            return $this->encode(['error' => true, 'message' => 'Authentication is required.']);
        }

        if (! $this->mayViewFinance($user)) {
            return $this->encode([
                'error' => true,
                'message' => 'You are not permitted to view tuition, balances, or payment records. Ask an authorised staff member to run this check.',
            ]);
        }

        try {
            $student = $this->entities->student($identifier, $this->currentSchool());
        } catch (EntityNotFoundException|AmbiguousEntityException $e) {
            return $this->encode(['error' => true, 'message' => $e->getMessage()]);
        }

        $denied = $this->selfAccessDenial($user, $student);

        if ($denied !== null) {
            return $this->encode(['error' => true, 'message' => $denied]);
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

    private function currentSchool(): ?School
    {
        $school = app(TenantContext::class)->getCurrentSchool();

        return $school instanceof School ? $school : null;
    }

    /**
     * Mirrors the finance gate on GetStudentFinancialSummaryTool.
     */
    private function mayViewFinance(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->can('View:Cashier')
            || $user->can('view_tuition_fees');
    }

    /**
     * Students may read their own account and nothing else.
     */
    private function selfAccessDenial(User $user, Student $student): ?string
    {
        if (! $user->isStudentRole()) {
            return null;
        }

        $own = app(ApiIdentityService::class)->studentFor($user);

        if (! $own instanceof Student) {
            return 'No student profile is associated with your account.';
        }

        if ((int) $own->id !== (int) $student->id) {
            return 'You can only view your own statement of account.';
        }

        $school = $this->currentSchool();

        if ($school instanceof School && ! $student->belongsToSchool($school) && (int) $student->institution_id !== (int) $school->id) {
            return 'Your student profile belongs to a different school.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
