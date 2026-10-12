<?php

declare(strict_types=1);

namespace App\Features\Concerns;

use App\Models\User;
use App\Services\FeatureAccessService;
use App\Services\FeatureToggleRegistry;
use Illuminate\Support\Lottery;

/**
 * Default resolution plus the managed-policy hook.
 *
 * A feature class used to read a `__laravel_null` row as "is this feature
 * on". That sentinel is also how Pennant serializes a null scope, so the public
 * enrollment form and the signed-in portal disagreed about the same feature.
 * Managed policy is the single source of truth now; legacy installations keep
 * working through the batch-cached legacy values.
 */
trait ResolvesFeatureToggle
{
    /** Compatibility for existing callers that invalidate legacy state. */
    public static function flushGlobalFeatureStates(): void
    {
        FeatureToggleRegistry::flushGlobalFeatureStates();
    }

    /**
     * Run an always-in-memory check ahead of any stored value.
     *
     * Returns null unless the feature is under managed policy, which is what
     * preserves existing installations during the roll out.
     */
    public function before(?User $scope): ?bool
    {
        return app(FeatureAccessService::class)->before($this->key(), $scope);
    }

    /**
     * Default resolution: legacy audiences still apply in compatibility mode.
     */
    public function resolve(User $scope): bool
    {
        if ($this->globalFeatureState() === false) {
            return false;
        }

        $audience = $this->audience();

        if ($audience === 'all') {
            return true;
        }

        if ($audience === 'student') {
            return $scope->isStudentRole();
        }

        if ($audience === 'faculty') {
            return $scope->isFaculty();
        }

        return false;
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
     * Check if the feature should be active for the user,
     * considering lottery-based rollout.
     */
    public function resolveWithLottery(User $scope): bool
    {
        $baseResolution = $this->resolve($scope);

        if (! $baseResolution) {
            return false;
        }

        $lottery = $this->lottery();

        if ($lottery === null) {
            return true;
        }

        return $lottery->choose();
    }

    /**
     * Legacy compatibility mode only; batch-cached per request.
     *
     * Kept under its original name: feature classes that override resolve()
     * call this directly.
     */
    private function globalFeatureState(): ?bool
    {
        $globalState = FeatureToggleRegistry::getGlobalFeatureState(static::class);

        if ($globalState === null) {
            return null;
        }

        $globalState = mb_trim((string) $globalState);

        if (in_array($globalState, ['false', '0', '', 'null'], true)) {
            return false;
        }

        if (in_array($globalState, ['true', '1'], true)) {
            return true;
        }

        $decodedValue = json_decode($globalState, true);

        if (! is_array($decodedValue) || ! array_key_exists('enabled', $decodedValue)) {
            return null;
        }

        return filter_var($decodedValue['enabled'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
