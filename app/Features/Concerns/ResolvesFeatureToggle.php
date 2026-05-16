<?php

declare(strict_types=1);

namespace App\Features\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;

/**
 * Provides common resolution logic for feature toggles.
 * Feature classes use this trait and override methods as needed.
 */
trait ResolvesFeatureToggle
{
    /**
     * Default resolution: check global activation state first, then audience matching.
     * Override in feature classes for custom logic.
     */
    public function resolve(User $scope): bool
    {
        // Check global activation state first
        $globalState = DB::table('features')
            ->where('name', static::class)
            ->where('scope', '__laravel_null')
            ->value('value');

        // If explicitly deactivated globally, deny access
        if ($globalState === 'false') {
            return false;
        }

        // If explicitly activated globally or no global state set, apply audience matching
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
}
