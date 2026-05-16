<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class StudentTuition implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'student-tuition';
    }

    public function name(): string
    {
        return 'Tuition & Fees';
    }

    public function summary(): ?string
    {
        return 'Keep an eye on balances and statements.';
    }

    public function audience(): string
    {
        return 'student';
    }

    public function badge(): ?string
    {
        return 'Finances';
    }

    public function accent(): ?string
    {
        return 'text-sky-500';
    }

    public function ctaLabel(): ?string
    {
        return 'Open Tuition';
    }

    public function ctaUrl(): ?string
    {
        return '/student/tuition';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Tuition & Fees',
                'summary' => 'Review balances and statement details.',
                'highlights' => ['Balance snapshot', 'Statement updates'],
                'stats' => [
                    ['label' => 'Route', 'value' => '/student/tuition'],
                    ['label' => 'Menu', 'value' => 'Tuition & Fees'],
                ],
                'badge' => 'Finances',
                'accent' => 'text-sky-500',
                'icon' => 'book-open',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Student';
    }
}
