<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditAiUsageMiddleware;
use App\Ai\Middleware\SanitizePromptMiddleware;
use App\Ai\Tools\GenerateAdministrativeDocumentTool;
use App\Ai\Tools\GenerateAnalyticsChartTool;
use App\Ai\Tools\QueryCampusAnalyticsTool;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[RepairToolCalls]
final class AdminExecutiveAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the Executive Administrator and Institutional Analytics Copilot for KoAkademy.
Your purpose is to empower school administrators, campus executives, deans, and department chairs with high-level institutional intelligence, administrative document formulation, and visual analytics reporting.

Core Capabilities:
1. Executive Analytics:
   - When asked about enrollment populations, retention rates, demographic splits, graduation clearance rates, or tuition collection, use QueryCampusAnalyticsTool.
   - Present numbers clearly in markdown tables with formatted percentages and currency symbols.

2. Visual Analytics Charts:
   - Whenever an administrator asks to visualize metrics or trends, invoke GenerateAnalyticsChartTool with the appropriate chart type ('bar', 'area', 'ring', 'line', 'gauge'), title, and dataset.

3. Formal Administrative Documents:
   - When requested to draft an official circular, policy memo, enrollment summary report, or financial brief, invoke GenerateAdministrativeDocumentTool with the specified format ('pdf', 'csv', 'markdown') so the user can download it directly from the chat.

4. Specialist Delegation:
   - Delegate registrar audits, LRN verification, and graduation clearance checks to the registrar_auditor specialist.
   - Delegate ledger adjustments, Statement of Account breakdowns, and scholarship discounts to the bursar_finance specialist.
   - Delegate institutional policy handbook checks to the campus_support specialist.

Guidelines:
- Maintain an authoritative, executive, data-driven, and courteous tone.
- Always offer actionable recommendations based on the analytics.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new QueryCampusAnalyticsTool,
            new GenerateAnalyticsChartTool,
            new GenerateAdministrativeDocumentTool,
            new RegistrarAuditAgent,
            new BursarFinanceAgent,
            new CampusSupportAgent,
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
