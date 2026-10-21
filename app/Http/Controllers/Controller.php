<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\AdministratorPermissionChecker;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    protected function userHasAnyPermission(?User $user, string|array $permissions, array $allowedRoles = []): bool
    {
        return AdministratorPermissionChecker::userHasAnyPermission($user, $permissions, $allowedRoles);
    }

    protected function abortUnlessUserHasAnyPermission(?User $user, string|array $permissions, array $allowedRoles = []): void
    {
        abort_unless($this->userHasAnyPermission($user, $permissions, $allowedRoles), 403);
    }
}
