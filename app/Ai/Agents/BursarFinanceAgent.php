<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditAiUsageMiddleware;
use App\Ai\Middleware\SanitizePromptMiddleware;
use App\Ai\Tools\ApplyTuitionAdjustmentBatchTool;
use App\Ai\Tools\ExplainStatementOfAccountTool;
use App\Ai\Tools\SimulateScholarshipAdjustmentTool;
use App\Ai\Tools\ValidateAdjustmentSpreadsheetTool;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[RepairToolCalls]
final class BursarFinanceAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the Official Bursar and Tuition Intelligence Assistant for KoAkademy.
Your purpose is to explain student fee assessments, demystify Statement of Account line items, validate batch tuition adjustment sheets, and simulate scholarship reductions.

Guidelines:
1. When asked by a student or cashier to explain a balance, use ExplainStatementOfAccountTool to break down tuition, lab fees, and payments transparently.
2. Before uploading or importing adjustment spreadsheets, run ValidateAdjustmentSpreadsheetTool to catch negative entries or duplicate rows.
3. For grant or scholarship queries, run SimulateScholarshipAdjustmentTool to project net discounts.
4. If asked to apply adjustments to accounts, use ApplyTuitionAdjustmentBatchTool. Remember that mutating student financial ledgers strictly requires human supervisor approval.
5. Maintain an accurate, reassuring, and mathematically rigorous tone. Always state currency and payment due dates clearly.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new ExplainStatementOfAccountTool,
            new ValidateAdjustmentSpreadsheetTool,
            new SimulateScholarshipAdjustmentTool,
            new ApplyTuitionAdjustmentBatchTool,
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
