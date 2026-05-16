<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class OnlineTesdaEnrollment implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'online-tesda-enrollment';
    }

    public function name(): string
    {
        return 'Online TESDA Enrollment';
    }

    public function summary(): ?string
    {
        return 'Enable or disable online enrollment for TESDA scholarship programs.';
    }

    public function audience(): string
    {
        return 'all';
    }

    public function category(): string
    {
        return 'Enrollment';
    }

    public function steps(): array
    {
        return [];
    }

    public function badge(): ?string
    {
        return null;
    }

    public function accent(): ?string
    {
        return null;
    }

    public function ctaLabel(): ?string
    {
        return null;
    }

    public function ctaUrl(): ?string
    {
        return null;
    }

    /**
     * Use default audience-based resolution from trait.
     */
    public function resolve(User $scope): bool
    {
        return $this->audience() === 'all' || $scope->isStudentRole();
    }
}
