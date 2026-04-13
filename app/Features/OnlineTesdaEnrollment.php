<?php

declare(strict_types=1);

namespace App\Features;

use App\Features\Onboarding\ResolvesOnboardingFeature;
use App\Models\User;

final class OnlineTesdaEnrollment
{
    use ResolvesOnboardingFeature;

    public function key(): string
    {
        return 'online-tesda-enrollment';
    }

    /**
     * @return array<int, string>
     */
    public function audienceRoles(): array
    {
        return ['student'];
    }

    /**
     * Resolve the feature's initial value.
     */
    public function resolve(User $scope): bool
    {
        return $this->resolveFromModel($scope);
    }
}
