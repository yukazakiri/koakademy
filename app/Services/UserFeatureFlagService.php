<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Pennant\Feature;

final class UserFeatureFlagService
{
    /**
     * Get all experimental feature keys.
     *
     * @return array<int, string>
     */
    public function featureKeys(): array
    {
        return collect(config('onboarding.experimental_feature_keys', []))
            ->filter(fn (mixed $featureKey): bool => is_string($featureKey) && $featureKey !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the experimental feature keys applicable to the given role.
     *
     * @return array<int, string>
     */
    public function featureKeysForRole(UserRole|string|null $role): array
    {
        $roleValues = $this->normalizeRoles($role);

        if ($roleValues === []) {
            return $this->featureKeys();
        }

        /** @var array<string, array<int, string>> $featureRoles */
        $featureRoles = config('onboarding.experimental_features_roles', []);

        return collect($this->featureKeys())
            ->filter(function (string $featureKey) use ($featureRoles, $roleValues): bool {
                $allowedRoles = $featureRoles[$featureKey] ?? [];

                return $allowedRoles === [] || collect($roleValues)->intersect($allowedRoles)->isNotEmpty();
            })
            ->values()
            ->all();
    }

    /**
     * Get role-scoped experimental feature labels for the UI.
     *
     * @return array<string, string>
     */
    public function featureOptionsForRole(UserRole|string|null $role): array
    {
        return collect($this->featureKeysForRole($role))
            ->mapWithKeys(fn (string $key): array => [$key => str_replace('-', ' ', str_replace('onboarding-', '', $key))])
            ->map(fn (string $label): string => ucwords($label))
            ->all();
    }

    /**
     * Get the active experimental feature keys for a user and role.
     *
     * @return array<int, string>
     */
    public function selectedFeatureKeysForUser(User $user, UserRole|string|null $role): array
    {
        return collect($this->featureKeysForRole($role))
            ->filter(fn (string $featureKey): bool => Feature::for($user)->active($featureKey))
            ->values()
            ->all();
    }

    /**
     * Sync a user's experimental feature overrides.
     *
     * @param  array<int, string>  $selectedFeatureKeys
     */
    public function syncFeatureOverrides(
        User $user,
        array $selectedFeatureKeys,
        UserRole|string|null $role,
        bool $resetToRoleDefaults = false,
    ): void {
        $allFeatureKeys = $this->featureKeys();
        $applicableFeatureKeys = $this->featureKeysForRole($role);
        $selected = array_values(array_intersect($selectedFeatureKeys, $applicableFeatureKeys));

        $scopedFeatures = Feature::for($user);

        $staleFeatureKeys = array_values(array_diff($allFeatureKeys, $applicableFeatureKeys));

        if ($staleFeatureKeys !== []) {
            $scopedFeatures->forget($staleFeatureKeys);
        }

        if ($resetToRoleDefaults) {
            if ($applicableFeatureKeys !== []) {
                $scopedFeatures->forget($applicableFeatureKeys);
            }

            if ($selected !== []) {
                $scopedFeatures->activate($selected);
            }

            return;
        }

        foreach ($applicableFeatureKeys as $featureKey) {
            if (in_array($featureKey, $selected, true)) {
                $scopedFeatures->activate($featureKey);

                continue;
            }

            $scopedFeatures->deactivate($featureKey);
        }
    }

    private function normalizeRoles(UserRole|string|null $role): array
    {
        $enum = $role instanceof UserRole ? $role : (is_string($role) ? UserRole::tryFrom($role) : null);

        if ($enum instanceof UserRole) {
            $roles = [$enum->value];

            if ($enum->isFaculty()) {
                $roles[] = 'faculty';
            }

            if ($enum->isStudent()) {
                $roles[] = 'student';
            }

            return array_values(array_unique($roles));
        }

        return is_string($role) && $role !== '' ? [$role] : [];
    }
}
