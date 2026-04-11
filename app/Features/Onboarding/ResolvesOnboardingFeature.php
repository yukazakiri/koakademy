<?php

declare(strict_types=1);

namespace App\Features\Onboarding;

use App\Models\OnboardingFeature;
use App\Models\User;
use Illuminate\Support\Lottery;
use Throwable;

trait ResolvesOnboardingFeature
{
    /**
     * The feature_key used to look up the OnboardingFeature model.
     * Override in each feature class.
     */
    abstract public function key(): string;

    /**
     * Roles that should be considered for audience matching.
     * Override to customize per feature.
     *
     * @return array<int, string>
     */
    public function audienceRoles(): array
    {
        return [];
    }

    /**
     * Optional lottery for incremental rollout.
     * Return null to disable, or Lottery::odds(1, 2) for 50% rollout.
     */
    public function lottery(): ?Lottery
    {
        return null;
    }

    /**
     * Resolve the feature's initial value from the OnboardingFeature model.
     * Call this from each feature class's resolve() method.
     */
    public function resolveFromModel(User $scope): bool
    {
        try {
            $feature = OnboardingFeature::query()
                ->where('feature_key', $this->key())
                ->first();

            if (! $feature || ! $feature->is_active) {
                return false;
            }

            if ($feature->audience === 'all') {
                return true;
            }

            $audienceRoles = $this->audienceRoles();

            if ($audienceRoles === []) {
                // Derive from the feature's audience field
                return match ($feature->audience) {
                    'student' => $scope->isStudentRole(),
                    'faculty' => $scope->isFaculty(),
                    default => false,
                };
            }

            $scopeRoles = $this->normalizeRoles($scope);

            return collect($audienceRoles)->intersect($scopeRoles)->isNotEmpty();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeRoles(User $user): array
    {
        $roles = [$user->role->value];

        if ($user->role->isFaculty()) {
            $roles[] = 'faculty';
        }

        if ($user->role->isStudent()) {
            $roles[] = 'student';
        }

        return array_values(array_unique($roles));
    }
}
