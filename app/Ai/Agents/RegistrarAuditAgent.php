<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditAiUsageMiddleware;
use App\Ai\Middleware\SanitizePromptMiddleware;
use App\Ai\Tools\AnalyzeTranscriptDocumentTool;
use App\Ai\Tools\AuditGraduationClearanceTool;
use App\Ai\Tools\AuditStudentProfileImportTool;
use App\Ai\Tools\BatchUpdateClearanceTool;
use App\Ai\Tools\SimulatePolicyImpactTool;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[RepairToolCalls]
final class RegistrarAuditAgent implements Agent, CanActAsTool, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function name(): string
    {
        return 'registrar_auditor';
    }

    public function description(): Stringable|string
    {
        return 'Audit student profiles, check graduation clearance holds, simulate enrollment policies, and inspect transcripts.';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the Official Registrar Compliance and Records Auditor for KoAkademy.
Your purpose is to assist the Registrar's Office, Admissions, and Deans in auditing student records, verifying transcript credentials, validating profile imports, and simulating policy rules.

Guidelines:
1. When validating profile import batches or admissions spreadsheets, run AuditStudentProfileImportTool to catch malformed LRNs and duplicates.
2. For student graduation checks, use AuditGraduationClearanceTool to identify outstanding obligations across all departments.
3. If instructed to clear holds or batch-update clearance, use BatchUpdateClearanceTool. Note that this requires explicit registrar confirmation before committing.
4. When inspecting uploaded prior school transcripts (TORs), use AnalyzeTranscriptDocumentTool to evaluate accredited course equivalents.
5. Provide precise, audit-defensible reporting and format discrepancies clearly in tables.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new AuditStudentProfileImportTool,
            new SimulatePolicyImpactTool,
            new AuditGraduationClearanceTool,
            new BatchUpdateClearanceTool,
            new AnalyzeTranscriptDocumentTool,
        ];
    }

    /**
     * Get the agent's middleware stack.
     */
    public function middleware(): array
    {
        return [
            new SanitizePromptMiddleware,
            new AuditAiUsageMiddleware,
        ];
    }
}
