<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class AiAdminExecutive implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function resolve(User $scope): bool
    {
        // This flag is new. Installations upgrading from an older release have
        // no historical Pennant state for it, so legacy mode preserves the
        // previous behavior: administrators already had this workspace.
        return $scope->canAccessAdminPortal();
    }

    public function key(): string
    {
        return 'ai-admin-executive';
    }

    public function name(): string
    {
        return 'AI Administrative Executive';
    }

    public function summary(): string
    {
        return 'Campus-wide analytics, executive reporting, and cross-department intelligence for administrative leaders.';
    }

    public function audience(): string
    {
        return 'administrator';
    }

    public function badge(): ?string
    {
        return 'Executive AI';
    }

    public function accent(): ?string
    {
        return 'text-violet-500';
    }

    public function ctaLabel(): ?string
    {
        return 'Open Executive Copilot';
    }

    public function ctaUrl(): ?string
    {
        return '/administrators/ai';
    }

    public function steps(): array
    {
        return [
            [
                'title' => 'Administrative Intelligence',
                'summary' => 'Consolidate enrollment, finance, clearance, and faculty signals into decision-ready briefs.',
                'highlights' => ['Institution-wide analytics', 'Executive document drafting', 'Cross-department audits'],
                'stats' => [
                    ['label' => 'Protocol', 'value' => 'Laravel AI SDK'],
                    ['label' => 'Audience', 'value' => 'Administrative Leadership'],
                ],
                'badge' => 'Executive AI',
                'accent' => 'text-violet-500',
                'icon' => 'sparkles',
                'image' => null,
            ],
        ];
    }

    public function category(): string
    {
        return 'Artificial Intelligence';
    }
}
