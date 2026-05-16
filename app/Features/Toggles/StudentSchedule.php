<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentSchedule implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-schedule';
    }

    public function name(): string
    {
        return 'Class Schedule';
    }

    public function summary(): ?string
    {
        return 'View weekly schedules and daily timing.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): ?string
    {
        return 'Schedule';
    }

    public function accent(): ?string
    {
        return 'text-indigo-500';
    }

    public function ctaLabel(): ?string
    {
        return 'Open Schedule';
    }

    public function ctaUrl(): ?string
    {
        return '/student/schedule';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Class Schedule',
                'summary' => 'Plan your week with class schedule details.',
                'highlights' => ['Weekly schedule', 'Room and instructor info'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/schedule'],
                    ['label' => 'Menu', 'value' => 'Class Schedule'],
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
        return 'Student';
    }
}
