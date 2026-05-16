<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultySettings implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-settings';
    }

    public function name(): string
    {
        return 'Settings';
    }

    public function summary(): ?string
    {
        return 'Update your profile and preferences.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): ?string
    {
        return 'Settings';
    }

    public function accent(): ?string
    {
        return 'text-indigo-500';
    }

    public function ctaLabel(): ?string
    {
        return 'Open Settings';
    }

    public function ctaUrl(): ?string
    {
        return '/faculty/profile';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Settings',
                'summary' => 'Manage your profile and preferences.',
                'highlights' => ['Profile updates', 'Account settings'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/faculty/profile'],
                    ['label' => 'Menu', 'value' => 'Settings'],
                ],
                'badge' => 'Settings',
                'accent' => 'text-indigo-500',
                'icon' => 'check-circle-2',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
