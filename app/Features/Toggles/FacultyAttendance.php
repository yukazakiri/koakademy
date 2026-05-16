<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyAttendance implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-attendance';
    }

    public function name(): string
    {
        return 'Attendance';
    }

    public function summary(): string
    {
        return 'Attendance tracking for each class session.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Academic Tools';
    }

    public function accent(): string
    {
        return 'text-sky-500';
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
                'title' => 'Attendance',
                'summary' => 'Track attendance quickly per class session.',
                'highlights' => ['Session attendance', 'Instant updates'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Attendance'],
                ],
                'badge' => 'Academic Tools',
                'accent' => 'text-sky-500',
                'icon' => 'check-circle-2',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
