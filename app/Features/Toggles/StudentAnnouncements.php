<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentAnnouncements implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-announcements';
    }

    public function name(): string
    {
        return 'Announcements';
    }

    public function summary(): ?string
    {
        return 'Read important campus updates and notices.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): ?string
    {
        return 'Announcements';
    }

    public function accent(): ?string
    {
        return 'text-amber-500';
    }

    public function ctaLabel(): ?string
    {
        return 'Open Announcements';
    }

    public function ctaUrl(): ?string
    {
        return '/student/announcements';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Announcements',
                'summary' => 'Stay up to date with school announcements.',
                'highlights' => ['Campus-wide updates', 'Event notices'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/announcements'],
                    ['label' => 'Menu', 'value' => 'Announcements'],
                ],
                'badge' => 'Announcements',
                'accent' => 'text-amber-500',
                'icon' => 'messages-square',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
