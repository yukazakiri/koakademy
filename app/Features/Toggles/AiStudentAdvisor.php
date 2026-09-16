<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class AiStudentAdvisor implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'ai-student-advisor';
    }

    public function name(): string
    {
        return 'AI Academic Advisor';
    }

    public function summary(): string
    {
        return 'Intelligent conversational assistant for student course recommendations, prerequisite verification, and schedule planning.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): string
    {
        return 'AI Assistant';
    }

    public function accent(): string
    {
        return 'text-indigo-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Advisor';
    }

    public function ctaUrl(): string
    {
        return '/student/dashboard';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'AI Academic Advisory',
                'summary' => 'Real-time guidance on degree progression and prerequisite eligibility.',
                'highlights' => ['Prerequisite validation', 'Schedule conflict detection', 'Enrollment plan staging'],
                'stats' => [
                    ['label' => 'Protocol', 'value' => 'Laravel AI SDK'],
                    ['label' => 'Audience', 'value' => 'Enrolled Students'],
                ],
                'badge' => 'AI Assistant',
                'accent' => 'text-indigo-500',
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
