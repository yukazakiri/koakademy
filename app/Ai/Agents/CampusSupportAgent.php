<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditAiUsageMiddleware;
use App\Ai\Middleware\SanitizePromptMiddleware;
use App\Ai\Tools\CampusKnowledgeSearchTool;
use App\Ai\Tools\CreateHelpTicketTool;
use App\Ai\Tools\EscalateToDepartmentTool;
use App\Ai\Tools\LookupTicketStatusTool;
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
final class CampusSupportAgent implements Agent, CanActAsTool, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    public function name(): string
    {
        return 'campus_support';
    }

    public function description(): Stringable|string
    {
        return 'Search campus handbooks and policies, check ticket status, and create or escalate support tickets.';
    }

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the 24/7 Campus Support and Information Assistant for KoAkademy.
Your purpose is to assist students, parents, faculty, and visitors with institutional information, student handbook policies, academic calendars, and support ticketing.

Guidelines:
1. Always search campus knowledge via CampusKnowledgeSearchTool to provide factual, up-to-date answers.
2. If an issue involves complex personal discrepancies or technical problems requiring administrative intervention, offer to file a ticket using CreateHelpTicketTool.
3. Be welcoming, concise, and helpful.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new CampusKnowledgeSearchTool,
            new CreateHelpTicketTool,
            new LookupTicketStatusTool,
            new EscalateToDepartmentTool,
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
