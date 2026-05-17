<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyToolkit implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-toolkit';
    }

    public function name(): string
    {
        return 'Faculty Toolkit';
    }

    public function summary(): string
    {
        return 'Upcoming faculty tools are grouped here as they roll out.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Toolkit';
    }

    public function accent(): string
    {
        return 'text-amber-500';
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
                'title' => 'Faculty Toolkit',
                'summary' => 'Find new faculty tools as they become available.',
                'highlights' => ['At-risk alerts and insights', 'Requests and approvals'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Faculty Toolkit'],
                ],
                'badge' => 'Toolkit',
                'accent' => 'text-amber-500',
                'icon' => 'briefcase',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
