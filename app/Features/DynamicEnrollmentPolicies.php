<?php

declare(strict_types=1);

namespace App\Features;

use Laravel\Pennant\Feature;

final class DynamicEnrollmentPolicies
{
    public function resolve(mixed $scope): bool
    {
        // A rollout applies to future staff scopes too, not only Pennant rows
        // that happened to exist when activateForEveryone() was called.
        return $scope !== null && Feature::for(null)->active(self::class);
    }
}
