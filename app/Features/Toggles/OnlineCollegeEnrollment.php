<?php

declare(strict_types=1);

namespace App\Features\Toggles;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Features\Contracts\FeatureToggle;
use App\Models\User;

final class OnlineCollegeEnrollment implements FeatureToggle
{
    use ResolvesFeatureToggle;

    public function key(): string
    {
        return 'online-college-enrollment';
    }

    public function name(): string
    {
        return 'Online College Enrollment';
    }

    public function summary(): string
    {
        return 'Enable or disable online enrollment for college degree programs.';
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
        if ($this->audience() === 'all') {
            return true;
        }

        return $scope->isStudentRole();
    }
}
