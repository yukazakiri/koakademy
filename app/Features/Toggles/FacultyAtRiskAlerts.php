<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyAtRiskAlerts implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-at-risk-alerts';
    }

    public function name(): string
    {
        return 'At-Risk Alerts';
    }

    public function summary(): ?string
    {
        return 'Early warning alerts for student performance.';
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
                'title' => 'At-Risk Alerts',
                'summary' => 'Monitor students who may need extra support.',
                'highlights' => ['Early warning signals', 'Actionable outreach'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'At-Risk Alerts'],
                ],
                'badge' => 'Toolkit',
                'accent' => 'text-rose-500',
                'icon' => 'trophy',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
