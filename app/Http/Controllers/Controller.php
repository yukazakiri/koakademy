<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Spatie\Permission\Models\Role;
use Throwable;

abstract class Controller
{
    use AuthorizesRequests;

    protected function userHasAnyPermission(?User $user, string|array $permissions, array $allowedRoles = []): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        // 1. Super admin and developer have universal administrative access
        if (
            $user->role === UserRole::SuperAdmin
            || $user->role === UserRole::Developer
            || $user->hasAnyRole([UserRole::SuperAdmin->value, UserRole::Developer->value, 'super_admin', 'developer'])
        ) {
            return true;
        }

        // 2. Check explicitly allowed roles
        if ($allowedRoles !== []) {
            $roleValues = array_map(
                fn ($r): string => $r instanceof BackedEnum ? (string) $r->value : (string) $r,
                $allowedRoles
            );
            if (in_array($user->role?->value, $roleValues, true) || $user->hasAnyRole($roleValues)) {
                return true;
            }
        }

        $requiredPermissions = is_array($permissions) ? $permissions : [$permissions];

        // 3. Check via Spatie permission methods & Laravel gate
        foreach ($requiredPermissions as $permission) {
            try {
                if ($user->can($permission) || $user->hasPermissionTo($permission, 'web')) {
                    return true;
                }
            } catch (Throwable) {
                // Ignore missing permissions in database table
            }
        }

        // 4. Fallback to permissions attached to user and roles in database
        $grantedPermissions = $user->getAllPermissions()->pluck('name')->values()->all();

        $roleName = $user->role?->value ?? $user->role;
        if (is_string($roleName) && $roleName !== '') {
            $userRole = UserRole::tryFrom($roleName);

            /** @var Role|null $spatieRole */
            $spatieRole = Role::query()
                ->with('permissions')
                ->where('name', $roleName)
                ->first();

            if (! $spatieRole && $userRole instanceof UserRole) {
                $spatieRole = Role::query()
                    ->with('permissions')
                    ->where('name', (string) $userRole->getLabel())
                    ->first();
            }

            if ($spatieRole instanceof Role) {
                $rolePerms = $spatieRole->permissions->pluck('name')->values()->all();
                $grantedPermissions = array_unique(array_merge($grantedPermissions, $rolePerms));
            }
        }

        return array_intersect($requiredPermissions, $grantedPermissions) !== [];
    }

    protected function abortUnlessUserHasAnyPermission(?User $user, string|array $permissions, array $allowedRoles = []): void
    {
        abort_unless($this->userHasAnyPermission($user, $permissions, $allowedRoles), 403);
    }
}
