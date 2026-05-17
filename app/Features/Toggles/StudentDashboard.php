<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentDashboard implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-dashboard';
    }

    public function name(): string
    {
        return 'Student Dashboard';
    }

    public function summary(): string
    {
        return 'Your quick view of classes and account status.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): string
    {
        return 'Dashboard';
    }

    public function accent(): string
    {
        return 'text-primary';
    }

    public function ctaLabel(): string
    {
        return 'Open Dashboard';
    }

    public function ctaUrl(): string
    {
        return '/student/dashboard';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Dashboard',
                'summary' => 'See your classes, balance, and alerts quickly.',
                'highlights' => ['Class overview', 'Account status'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/dashboard'],
                    ['label' => 'Menu', 'value' => 'Dashboard'],
                ],
                'badge' => 'Dashboard',
                'accent' => 'text-primary',
                'icon' => 'stars',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
