<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentDeveloperMode implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-developer-mode';
    }

    public function name(): string
    {
        return 'Developer Mode';
    }

    public function summary(): string
    {
        return 'Access developer tools and debugging features.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): string
    {
        return 'Developer';
    }

    public function accent(): string
    {
        return 'text-rose-500';
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
                'summary' => 'Access developer tools and debugging features.',
                'highlights' => ['Developer tools', 'Debugging features'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Developer Mode'],
                ],
                'badge' => 'Developer',
                'accent' => 'text-rose-500',
                'icon' => 'code',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
