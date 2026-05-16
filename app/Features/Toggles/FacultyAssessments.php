<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyAssessments implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-assessments';
    }

    public function name(): string
    {
        return 'Assessments';
    }

    public function summary(): ?string
    {
        return 'Create quizzes, rubrics, and grading queues.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): ?string
    {
        return 'Toolkit';
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
                'title' => 'Assessments',
                'summary' => 'Organize assessment workflows in one place.',
                'highlights' => ['Quizzes and rubrics', 'Grading queue overview'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Assessments'],
                ],
                'badge' => 'Toolkit',
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
