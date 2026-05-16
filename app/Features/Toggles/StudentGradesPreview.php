<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentGradesPreview implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-grades-preview';
    }

    public function name(): string
    {
        return 'Grades Preview';
    }

    public function summary(): string
    {
        return 'Preview your current grades and academic performance.';
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
        return 'Open Grades';
    }

    public function ctaUrl(): string
    {
        return '/student/grades';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Grades Preview',
                'summary' => 'Preview your current grades and academic performance.',
                'highlights' => ['Current grade overview', 'Academic performance summary'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/grades'],
                    ['label' => 'Menu', 'value' => 'Grades Preview'],
                ],
                'badge' => 'Academics',
                'accent' => 'text-emerald-500',
                'icon' => 'clipboard-list',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
