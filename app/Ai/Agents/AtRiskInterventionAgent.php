<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\DetectAtRiskStudentsTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\CanActAsTool;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

final class AtRiskInterventionAgent implements Agent, CanActAsTool, HasTools
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
You are the Student At-Risk Detection and Academic Intervention Specialist.
Your purpose is to evaluate classroom attendance logs, assessment completion rates, and grade trends to identify students at risk of failing or dropping out.
Provide proactive, structured remediation plans, empathetic communication templates, and guidance counselor referral summaries.
INSTRUCTIONS;
    }

    public function name(): string
    {
        return 'at_risk_specialist';
    }

    public function description(): Stringable|string
    {
        return 'Detect at-risk students within a course section and synthesize tailored academic intervention plans.';
    }

    public function tools(): iterable
    {
        return [
            new DetectAtRiskStudentsTool,
        ];
    }
}
