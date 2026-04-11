<?php

declare(strict_types=1);

namespace App\Features\Onboarding;

use App\Models\User;

final class FacultyAtRiskAlerts
{
    use ResolvesOnboardingFeature;

    public function key(): string
    {
        return 'onboarding-faculty-at-risk-alerts';
    }

    /**
     * @return array<int, string>
     */
    public function audienceRoles(): array
    {
        return ['faculty'];
    }

    /**
     * Resolve the feature's initial value.
     */
    public function resolve(User $scope): bool
    {
        // Incremental rollout: uncomment to enable for 50% of matched audience
        // if (($lottery = $this->lottery()) !== null && $lottery->make() === true) {
        //     return true;
        // }

        return $this->resolveFromModel($scope);
    }
}
