<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyDashboard implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-dashboard';
    }

    public function name(): string
    {
        return 'Faculty Dashboard';
    }

    public function summary(): ?string
    {
        return 'Your command center for day-to-day teaching updates.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): ?string
    {
        return 'Dashboard';
    }

    public function accent(): ?string
    {
        return 'text-primary';
    }

    public function ctaLabel(): ?string
    {
        return 'Open Dashboard';
    }

    public function ctaUrl(): ?string
    {
        return '/faculty/dashboard';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'summary' => "Check today's highlights and stay on top of priorities.",
                'highlights' => ['Faculty dashboard overview', 'Daily priorities and alerts'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/faculty/dashboard'],
                    ['label' => 'Menu', 'value' => 'Dashboard'],
                ],
                'badge' => 'Dashboard',
                'accent' => 'text-primary',
                'icon' => 'sparkles',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
