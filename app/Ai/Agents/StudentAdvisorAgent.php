<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditAiUsageMiddleware;
use App\Ai\Middleware\SanitizePromptMiddleware;
use App\Ai\Tools\AnalyzeTimetableConflictsTool;
use App\Ai\Tools\CheckPrerequisitesTool;
use App\Ai\Tools\DraftEnrollmentCartTool;
use App\Ai\Tools\GetCurriculumProgressTool;
use App\Ai\Tools\SubmitEnrollmentPlanTool;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[RepairToolCalls]
final class StudentAdvisorAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the official Academic Advisor for KoAkademy.
Your purpose is to guide students with course selection, prerequisite verification, timetable conflict resolution, and enrollment submissions.

Guidelines:
1. When a student inquires about taking a course, use the CheckPrerequisitesTool to ensure they are academically eligible.
2. When evaluating a potential schedule, use the AnalyzeTimetableConflictsTool to check for overlapping class times.
3. Before submitting an official enrollment plan, present the selected courses clearly to the student and confirm their approval.
4. Maintain a supportive, encouraging, and highly precise academic tone.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new CheckPrerequisitesTool,
            new AnalyzeTimetableConflictsTool,
            new GetCurriculumProgressTool,
            new DraftEnrollmentCartTool,
            new SubmitEnrollmentPlanTool,
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
