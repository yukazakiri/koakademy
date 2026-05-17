<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyAnnouncements implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-announcements';
    }

    public function name(): string
    {
        return 'Announcements';
    }

    public function summary(): string
    {
        return 'Post and read announcements quickly.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Announcements';
    }

    public function accent(): string
    {
        return 'text-amber-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Announcements';
    }

    public function ctaUrl(): string
    {
        return '/faculty/announcements';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Announcements',
                'summary' => 'Share updates and read new announcements.',
                'highlights' => ['Announcements feed', 'Share updates'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/faculty/announcements'],
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
        return 'Faculty';
    }
}
