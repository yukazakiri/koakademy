<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyInsights implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-insights';
    }

    public function name(): string
    {
        return 'Insights';
    }

    public function summary(): ?string
    {
        return 'Class analytics and trends at a glance.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): ?string
    {
        return 'Toolkit';
    }

    public function accent(): ?string
    {
        return 'text-indigo-500';
    }

    public function ctaLabel(): ?string
    {
        return null;
    }

    public function ctaUrl(): ?string
    {
        return null;
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Insights',
                'summary' => 'Discover trends and performance summaries.',
                'highlights' => ['Class analytics', 'Progress trends'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Insights'],
                ],
                'badge' => 'Toolkit',
                'accent' => 'text-indigo-500',
                'icon' => 'trophy',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
