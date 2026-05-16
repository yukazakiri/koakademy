<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentSettings implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-settings';
    }

    public function name(): string
    {
        return 'Settings';
    }

    public function summary(): ?string
    {
        return 'Update your profile and account preferences.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): ?string
    {
        return 'Settings';
    }

    public function accent(): ?string
    {
        return 'text-rose-500';
    }

    public function ctaLabel(): ?string
    {
        return 'Open Settings';
    }

    public function ctaUrl(): ?string
    {
        return '/student/profile';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Settings',
                'summary' => 'Manage your profile and preferences.',
                'highlights' => ['Profile updates', 'Account preferences'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/profile'],
                    ['label' => 'Menu', 'value' => 'Settings'],
                ],
                'badge' => 'Settings',
                'accent' => 'text-rose-500',
                'icon' => 'check-circle-2',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
