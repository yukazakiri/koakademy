<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentOnlineResources implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-online-resources';
    }

    public function name(): string
    {
        return 'Online Resources';
    }

    public function summary(): string
    {
        return 'Quick access to categorized school and class online resource links.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): string
    {
        return 'Resources';
    }

    public function accent(): string
    {
        return 'text-sky-500';
    }

    public function ctaLabel(): string
    {
        return 'Open Online Resources';
    }

    public function ctaUrl(): string
    {
        return '/student/resources';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function steps(): array
    {
        return [
            [
                'title' => 'Online Resources',
                'summary' => 'Browse curated school and class links organized by category, or scan QR posters around campus.',
                'highlights' => ['Categorized school & class links', 'Instant QR code access', 'Fast search across resources'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/resources'],
                    ['label' => 'Menu', 'value' => 'Online Resources'],
                ],
                'badge' => 'Resources',
                'accent' => 'text-sky-500',
                'icon' => 'link',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
