<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class AiFacultyAssistant implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'ai-faculty-assistant';
    }

    public function name(): string
    {
        return 'AI Faculty Copilot';
    }

    public function summary(): string
    {
        return 'Assist academic staff with rubric creation, formative submission evaluation, and at-risk student intervention.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'AI Copilot';
    }

    public function accent(): string
    {
        return 'text-emerald-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Copilot';
    }

    public function ctaUrl(): string
    {
        return '/faculty/dashboard';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'AI Faculty Copilot',
                'summary' => 'Accelerate rubric design, grading feedback, and student risk interventions.',
                'highlights' => ['Rubric generation', 'Submission feedback draft', 'At-risk early warning'],
                'stats' => [
                    ['label' => 'Audience', 'value' => 'Faculty & Instructors'],
                    ['label' => 'Gate', 'value' => 'Approval Required on Grades'],
                ],
                'badge' => 'AI Copilot',
                'accent' => 'text-emerald-500',
                'icon' => 'sparkles',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Artificial Intelligence';
    }
}
