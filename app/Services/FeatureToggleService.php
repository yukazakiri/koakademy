<?php

declare(strict_types=1);

namespace App\Services;

use App\Features\Concerns\ResolvesFeatureToggle;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

/**
 * Service for managing feature toggle state via Pennant.
 * Handles global activation, deactivation, and per-user overrides.
 */
final class FeatureToggleService
{
    /**
     * Activate a feature globally (for everyone).
     */
    public function activateGlobally(string $featureKey): void
    {
        if ($this->managed($featureKey)) {
            $this->setManagedEnabled($featureKey, true);

            return;
        }
        $featureClass = FeatureToggleRegistry::classForKey($featureKey);
        $featureRef = $featureClass ?? $featureKey;

        $this->setGlobalState($featureRef, true);
    }

    /**
     * Deactivate a feature globally (for everyone).
     */
    public function deactivateGlobally(string $featureKey): void
    {
        if ($this->managed($featureKey)) {
            $this->setManagedEnabled($featureKey, false);

            return;
        }
        $featureClass = FeatureToggleRegistry::classForKey($featureKey);
        $featureRef = $featureClass ?? $featureKey;

        $this->setGlobalState($featureRef, false);
    }

    /**
     * Activate a feature for a specific user.
     */
    public function activateForUser(string $featureKey, User $user): void
    {
        if ($this->managed($featureKey)) {
            $this->setManagedUser($featureKey, $user, 'allow');

            return;
        }
        $featureClass = FeatureToggleRegistry::classForKey($featureKey);
        $featureRef = $featureClass ?? $featureKey;

        Feature::for($user)->activate($featureRef);
    }

    /**
     * Deactivate a feature for a specific user.
     */
    public function deactivateForUser(string $featureKey, User $user): void
    {
        if ($this->managed($featureKey)) {
            $this->setManagedUser($featureKey, $user, 'deny');

            return;
        }
        $featureClass = FeatureToggleRegistry::classForKey($featureKey);
        $featureRef = $featureClass ?? $featureKey;

        Feature::for($user)->deactivate($featureRef);
    }

    /**
     * Purge all per-user overrides for a feature.
     */
    public function purgeOverrides(string $featureKey): void
    {
        // Legacy provenance is unknowable. Do not delete automatic evaluations
        // under a label claiming they are explicit assignments.
        DB::table('feature_assignments')->where('feature_key', FeatureToggleRegistry::canonicalKey($featureKey))
            ->where('subject_type', 'user')->delete();
        app(FeatureManagementService::class)->flush();
    }

    /**
     * Check if a feature is globally activated.
     * Returns true by default (fresh install = all features active).
     */
    public function isGloballyActivated(string $featureKey): bool
    {
        if ($this->managed($featureKey)) {
            return (bool) app(FeatureAccessService::class)->definition($featureKey)->enabled;
        }
        $featureClass = FeatureToggleRegistry::classForKey($featureKey);

        if ($featureClass === null) {
            return false;
        }

        $row = DB::table('features')
            ->where('name', $featureClass)
            ->where('scope', '__laravel_null')
            ->first();

        // No global state row = active by default (fresh install)
        if ($row === null) {
            return true;
        }

        return $row->value === 'true';
    }

    /**
     * Count users with explicit overrides for a feature.
     */
    public function getUserOverrideCount(string $featureKey): int
    {
        return DB::table('feature_assignments')->where('feature_key', FeatureToggleRegistry::canonicalKey($featureKey))
            ->where('subject_type', 'user')->count();
    }

    /**
     * Get users with explicit overrides for a feature.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getOverriddenUsers(string $featureKey): array
    {
        return array_values(array_filter(app(FeatureManagementService::class)->assignments(FeatureToggleRegistry::canonicalKey($featureKey)),
            fn (array $row): bool => $row['subject_type'] === 'user'));
    }

    /**
     * Get the current state of a feature for admin display.
     *
     * @return array<string, mixed>
     */
    public function getFeatureState(string $featureKey): array
    {
        $featureClass = FeatureToggleRegistry::classForKey($featureKey);

        if ($featureClass === null) {
            return [
                'is_globally_activated' => false,
                'override_count' => 0,
                'has_class' => false,
            ];
        }

        return [
            'is_globally_activated' => $this->isGloballyActivated($featureKey),
            'override_count' => $this->getUserOverrideCount($featureKey),
            'has_class' => true,
        ];
    }

    /**
     * Store the global activation state for a feature.
     * Uses a __laravel_null scope row so class-based resolve() methods can check it.
     * Also uses Pennant's activateForEveryone/deactivateForEveryone to update existing
     * per-user rows and clear Pennant's in-memory cache.
     */
    private function setGlobalState(string $featureRef, bool $active): void
    {
        // Insert/update the __laravel_null row for class-based resolve() check
        $existing = DB::table('features')
            ->where('name', $featureRef)
            ->where('scope', '__laravel_null')
            ->first();

        if ($existing) {
            DB::table('features')
                ->where('name', $featureRef)
                ->where('scope', '__laravel_null')
                ->update([
                    'value' => json_encode($active, JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('features')->insert([
                'name' => $featureRef,
                'scope' => '__laravel_null',
                'value' => json_encode($active, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        ResolvesFeatureToggle::flushGlobalFeatureStates();

        // Use Pennant's API to update existing per-user rows AND clear the cache
        if ($active) {
            Feature::activateForEveryone($featureRef);
        } else {
            Feature::deactivateForEveryone($featureRef);
        }
    }

    private function managed(string $key): bool
    {
        return app(FeatureAccessService::class)->definition($key)?->mode === 'enforced';
    }

    private function setManagedEnabled(string $key, bool $enabled): void
    {
        $actor = auth()->user();
        abort_unless(FeatureManagementService::canManage($actor), 403);
        $service = app(FeatureManagementService::class);
        $data = $service->policyPayload(app(FeatureAccessService::class)->definition($key));
        $data['enabled'] = $enabled;
        $service->updatePolicy(FeatureToggleRegistry::canonicalKey($key), $data, $actor);
    }

    private function setManagedUser(string $key, User $user, string $effect): void
    {
        $actor = auth()->user();
        abort_unless(FeatureManagementService::canManage($actor), 403);
        app(FeatureManagementService::class)->assign(FeatureToggleRegistry::canonicalKey($key), 'user', (string) $user->id, $effect, $actor);
    }
}
