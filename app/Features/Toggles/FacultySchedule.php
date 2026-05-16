<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultySchedule implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-schedule';
    }

    public function name(): string
    {
        return 'My Schedule';
    }

    public function summary(): string
    {
        return 'See your upcoming classes and room assignments.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Schedule';
    }

    public function accent(): string
    {
        return 'text-indigo-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Schedule';
    }

    public function ctaUrl(): string
    {
        return '/faculty/schedule';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'My Schedule',
                'summary' => 'Plan your week with schedule and room details.',
                'highlights' => ['Weekly class lineup', 'Room and time details'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/faculty/schedule'],
                    ['label' => 'Menu', 'value' => 'My Schedule'],
                ],
                'badge' => 'Schedule',
                'accent' => 'text-indigo-500',
                'icon' => 'calendar-days',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
