<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyDeveloperMode implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-developer-mode';
    }

    public function name(): string
    {
        return 'Developer Mode';
    }

    public function summary(): string
    {
        return 'Advanced tools and debugging features for faculty developers.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Developer';
    }

    public function accent(): string
    {
        return 'text-gray-500';
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
                'title' => 'Developer Mode',
                'summary' => 'Access advanced debugging and development tools.',
                'highlights' => ['Debug utilities', 'Development tools'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Opt-in'],
                    ['label' => 'Menu', 'value' => 'Developer Mode'],
                ],
                'badge' => 'Developer',
                'accent' => 'text-gray-500',
                'icon' => 'code',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
