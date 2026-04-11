<?php

declare(strict_types=1);

namespace App\Features;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Lottery;

final class StudentSignaturePad
{
    /**
     * Resolve the feature's initial value.
     *
     * Admins get the feature by default. Other roles can be
     * opted-in via Feature::activate() or incremental rollout
     * using Lottery.
     */
    public function resolve(User $scope): bool
    {
        if ($scope->role === UserRole::Admin) {
            return true;
        }

        // Incremental rollout: uncomment to enable for 50% of faculty
        // return Lottery::odds(1, 2)->make();
        return false;
    }
}
