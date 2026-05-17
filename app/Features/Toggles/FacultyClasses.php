<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyClasses implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-classes';
    }

    public function name(): string
    {
        return 'My Classes';
    }

    public function summary(): string
    {
        return 'Manage your classes and student lists.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Classes';
    }

    public function accent(): string
    {
        return 'text-sky-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Classes';
    }

    public function ctaUrl(): string
    {
        return '/faculty/classes';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'My Classes',
                'summary' => 'Open class rosters, grades, and materials quickly.',
                'highlights' => ['Class roster management', 'Gradebook shortcuts'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/faculty/classes'],
                    ['label' => 'Menu', 'value' => 'My Classes'],
                ],
                'badge' => 'Classes',
                'accent' => 'text-sky-500',
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
