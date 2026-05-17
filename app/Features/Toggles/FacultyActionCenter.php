<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyActionCenter implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-action-center';
    }

    public function name(): string
    {
        return 'Action Center';
    }

    public function summary(): string
    {
        return 'Review follow-ups and action items in one place.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Action Center';
    }

    public function accent(): string
    {
        return 'text-emerald-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Action Center';
    }

    public function ctaUrl(): string
    {
        return '/faculty/action-center';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Action Center',
                'summary' => 'Prioritize tasks, alerts, and upcoming responsibilities.',
                'highlights' => ['Open tasks and deadlines', 'Quick access follow-ups'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/faculty/action-center'],
                    ['label' => 'Menu', 'value' => 'Action Center'],
                ],
                'badge' => 'Action Center',
                'accent' => 'text-emerald-500',
                'icon' => 'zap',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
