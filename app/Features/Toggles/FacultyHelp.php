<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyHelp implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-help';
    }

    public function name(): string
    {
        return 'Help & Support';
    }

    public function summary(): string
    {
        return 'Get help or submit support tickets.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Support';
    }

    public function accent(): string
    {
        return 'text-emerald-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Help';
    }

    public function ctaUrl(): string
    {
        return '/faculty/help';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Help & Support',
                'summary' => 'Reach support when you need assistance.',
                'highlights' => ['Help center access', 'Ticket submissions'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/faculty/help'],
                    ['label' => 'Menu', 'value' => 'Help & Support'],
                ],
                'badge' => 'Support',
                'accent' => 'text-emerald-500',
                'icon' => 'users',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
