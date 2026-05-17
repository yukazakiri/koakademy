<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentClasses implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-classes';
    }

    public function name(): string
    {
        return 'My Academics';
    }

    public function summary(): string
    {
        return 'Review your enrolled subjects and academics.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): string
    {
        return 'Academics';
    }

    public function accent(): string
    {
        return 'text-emerald-500';
    }

    public function ctaLabel(): string
    {
        return 'Open My Academics';
    }

    public function ctaUrl(): string
    {
        return '/student/classes';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'My Academics',
                'summary' => 'Track your subjects and class details.',
                'highlights' => ['Subject list', 'Class detail views'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/classes'],
                    ['label' => 'Menu', 'value' => 'My Academics'],
                ],
                'badge' => 'Academics',
                'accent' => 'text-emerald-500',
                'icon' => 'graduation-cap',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
