<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Role;

abstract class Controller
{
    use AuthorizesRequests;

    protected function userHasAnyPermission(?User $user, string|array $permissions): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $requiredPermissions = is_array($permissions) ? $permissions : [$permissions];
        $grantedPermissions = $user->getAllPermissions()->pluck('name')->values()->all();

        if ($grantedPermissions === []) {
            $roleName = $user->role?->value ?? $user->role;

            if (is_string($roleName) && $roleName !== '') {
                $userRole = \App\Enums\UserRole::tryFrom($roleName);

                /** @var Role|null $spatieRole */
                $spatieRole = Role::query()
                    ->with('permissions')
                    ->where('name', $roleName)
                    ->first();

                if (! $spatieRole && $userRole instanceof \App\Enums\UserRole) {
                    $spatieRole = Role::query()
                        ->with('permissions')
                        ->where('name', (string) $userRole->getLabel())
                        ->first();
                }

                if ($spatieRole instanceof Role) {
                    $grantedPermissions = $spatieRole->permissions->pluck('name')->values()->all();
                }
            }
        }

        return array_intersect($requiredPermissions, $grantedPermissions) !== [];
    }

    protected function abortUnlessUserHasAnyPermission(?User $user, string|array $permissions): void
    {
        abort_unless($this->userHasAnyPermission($user, $permissions), 403);
    }
}
