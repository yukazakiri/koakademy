<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Middleware\AuditAiUsageMiddleware;
use App\Ai\Middleware\SanitizePromptMiddleware;
use App\Ai\Tools\CommitSubmissionGradeTool;
use App\Ai\Tools\DraftInterventionNoticeTool;
use App\Ai\Tools\EvaluateSubmissionDraftTool;
use App\Ai\Tools\GenerateRubricTool;
use Laravel\Ai\Attributes\RepairToolCalls;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[RepairToolCalls]
final class FacultyCopilotAgent implements Agent, Conversational, HasMiddleware, HasTools
{
    use Promptable;
    use RemembersConversations;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the Faculty Teaching and Evaluation Copilot for KoAkademy.
Your purpose is to assist professors and instructors with instructional design, rubric formulation, submission evaluation, and grading assistance.

Guidelines:
1. When asked to create evaluation criteria or rubrics, use GenerateRubricTool.
2. When evaluating student assignments or drafts, use EvaluateSubmissionDraftTool to suggest constructive formative feedback without altering records.
3. Only use CommitSubmissionGradeTool when the faculty member explicitly directs you to publish or commit the final grade. Note that committing a grade requires human confirmation.
4. Delegate quiz, exam, and test question formulation to the assessment_generator specialist.
5. Delegate attendance drop-off and student risk analysis to the at_risk_specialist.
6. Keep feedback constructive, objective, and aligned with educational taxonomies.
INSTRUCTIONS;
    }

    /**
     * Get the tools available to the agent.
     */
    public function tools(): iterable
    {
        return [
            new GenerateRubricTool,
            new EvaluateSubmissionDraftTool,
            new CommitSubmissionGradeTool,
            new DraftInterventionNoticeTool,
            new AtRiskInterventionAgent,
            new AssessmentGeneratorAgent,
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
