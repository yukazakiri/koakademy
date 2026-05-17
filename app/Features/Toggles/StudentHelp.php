<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentHelp implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-help';
    }

    public function name(): string
    {
        return 'Help & Support';
    }

    public function summary(): string
    {
        return 'Get help and submit support tickets.';
    }

    public function audience(): string
    {
        return 'student';
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
        return '/student/help';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Help & Support',
                'summary' => 'Reach support whenever you need help.',
                'highlights' => ['Help center access', 'Support requests'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/help'],
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
        return 'Student';
    }
}
