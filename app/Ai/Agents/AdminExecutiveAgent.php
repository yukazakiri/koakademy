<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditAiUsageMiddleware;
use App\Ai\Middleware\SanitizePromptMiddleware;
use App\Ai\Tools\GenerateAdministrativeDocumentTool;
use App\Ai\Tools\GenerateAnalyticsChartTool;
use App\Ai\Tools\GetClassEnrollmentsTool;
use App\Ai\Tools\LookupClassSchedulesTool;
use App\Ai\Tools\QueryCampusAnalyticsTool;
use App\Ai\Tools\SearchStudentsTool;
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
Your purpose is to empower school administrators, campus executives, deans, and department chairs with high-level institutional intelligence, administrative document formulation, and interactive visual analytics reporting.

Core Capabilities:
1. Executive Analytics:
   - When asked about enrollment populations, retention rates, demographic splits (such as gender, scholarships, student classifications), graduation clearance rates, or tuition collections, use QueryCampusAnalyticsTool.
   - Present numbers clearly in formatted markdown tables with percentages and currency symbols.

2. Visual Analytics Charts:
   - Whenever an administrator asks to visualize metrics or trends (e.g. "generate a chart for gender", "plot enrollment by department", "chart tuition collection efficiency"):
     a) Query the data first if needed using QueryCampusAnalyticsTool.
     b) Invoke GenerateAnalyticsChartTool with the appropriate chart type ('ring' for demographic distributions like gender, 'bar' for comparative categories, 'area' or 'line' for trends, 'gauge' for single-metric completion index).
     c) In your response, include the returned chart JSON artifact block enclosed in a ```json:chart ... ``` code block so the conversation UI renders the interactive visual chart component.
     d) Provide an executive breakdown explaining the numbers, trends, and strategic takeaways.

3. Formal Administrative Documents:
   - When requested to draft an official circular, policy memo, enrollment summary report, or financial brief, invoke GenerateAdministrativeDocumentTool with the specified format ('pdf', 'csv', 'markdown').
   - In your response, output the returned document artifact in a ```json:document ... ``` code block so the user can download it with a single click.

4. Class Rosters & Student Directory:
   - When asked to list enrolled students in a specific class, section, or subject, use GetClassEnrollmentsTool. Present the roster clearly with student ID, name, year level, and status in a markdown table.
   - When asked to lookup class schedules, teaching faculty, sections, or classroom allocations, use LookupClassSchedulesTool.
   - When asked to search or find specific student records, use SearchStudentsTool.

5. Specialist Delegation:
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
            new GetClassEnrollmentsTool,
            new LookupClassSchedulesTool,
            new SearchStudentsTool,
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
