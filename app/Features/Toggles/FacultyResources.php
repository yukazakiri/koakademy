<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyResources implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-resources';
    }

    public function name(): string
    {
        return 'Resources';
    }

    public function summary(): ?string
    {
        return 'Library and teaching resources.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): ?string
    {
        return 'Resources';
    }

    public function accent(): ?string
    {
        return 'text-primary';
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
                'title' => 'Resources',
                'summary' => 'Access teaching resources and library materials.',
                'highlights' => ['Resource library', 'Teaching materials'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Resources'],
                ],
                'badge' => 'Resources',
                'accent' => 'text-primary',
                'icon' => 'book-open',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
