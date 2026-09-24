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
     * In-memory cache of global feature states (__laravel_null scope) to prevent
     * redundant queries when multiple features are evaluated in a single request.
     *
     * @var array<string, mixed>|null
     */
    private static ?array $globalFeatureStates = null;

    /**
     * Flush the cached global feature states.
     */
    public static function flushGlobalFeatureStates(): void
    {
        self::$globalFeatureStates = null;
    }

    private function globalFeatureState(): ?bool
    {
        if (self::$globalFeatureStates === null) {
            self::$globalFeatureStates = DB::table('features')
                ->where('scope', '__laravel_null')
                ->pluck('value', 'name')
                ->all();
        }

        $globalState = self::$globalFeatureStates[static::class] ?? null;

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
