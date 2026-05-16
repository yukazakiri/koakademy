<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyGrades implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-grades';
    }

    public function name(): string
    {
        return 'Grades & Reports';
    }

    public function summary(): ?string
    {
        return 'Grade management and report exports.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): ?string
    {
        return 'Academic Tools';
    }

    public function accent(): ?string
    {
        return 'text-emerald-500';
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
                'title' => 'Grades & Reports',
                'summary' => 'Manage grades and generate reports.',
                'highlights' => ['Grades workflow', 'Report exports'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Grades & Reports'],
                ],
                'badge' => 'Academic Tools',
                'accent' => 'text-emerald-500',
                'icon' => 'clipboard-list',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
