<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;

final class FacultyForms implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'faculty-forms';
    }

    public function name(): string
    {
        return 'Faculty Forms';
    }

    public function summary(): ?string
    {
        return 'Request forms and approvals in one place.';
    }

    public function audience(): string
    {
        return 'faculty';
    }

    public function badge(): ?string
    {
        return 'Forms';
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
                'title' => 'Faculty Forms',
                'summary' => 'Access leave requests and requisition forms.',
                'highlights' => ['Leave requests', 'Requisitions and forms'],
                'stats' => [
                    ['label' => 'Status', 'value' => 'Coming soon'],
                    ['label' => 'Menu', 'value' => 'Faculty Forms'],
                ],
                'badge' => 'Forms',
                'accent' => 'text-rose-500',
                'icon' => 'clipboard-list',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Faculty';
    }
}
