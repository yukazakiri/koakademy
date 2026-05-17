<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyRequestsApprovals implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-requests-approvals';
    }

    public function name(): string
    {
        return 'Requests & Approvals';
    }

    public function summary(): string
    {
        return 'Excusals and approvals in one workflow.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): string
    {
        return 'Toolkit';
    }

    public function accent(): string
    {
        return 'text-amber-500';
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
                'title' => 'Requests & Approvals',
                'summary' => 'Handle requests and approvals quickly.',
                'highlights' => ['Excusals and make-up requests', 'Approval tracking'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Requests & Approvals'],
                ],
                'badge' => 'Toolkit',
                'accent' => 'text-amber-500',
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
