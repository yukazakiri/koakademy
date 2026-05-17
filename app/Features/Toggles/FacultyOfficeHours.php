<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyOfficeHours implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-office-hours';
    }

    public function name(): string
    {
        return 'Office Hours';
    }

    public function summary(): string
    {
        return 'Student appointment booking tools.';
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
                'title' => 'Office Hours',
                'summary' => 'Plan office hours and manage appointments.',
                'highlights' => ['Booking preferences', 'Appointment visibility'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Office Hours'],
                ],
                'badge' => 'Toolkit',
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
