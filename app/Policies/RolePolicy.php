<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Role');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Role');
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Role');
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Role');
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Role');
    }

    /**
     * Determine whether the user can restore the role.
     */
    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Role');
    }

    /**
     * Determine whether the user can permanently delete the role.
     */
    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Role');
    }

    /**
     * Determine whether the user can permanently delete any roles.
     */
    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Role');
    }

    /**
     * Determine whether the user can restore any roles.
     */
    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Role');
    }

    /**
     * Determine whether the user can duplicate roles.
     */
    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Role');
    }

    /**
     * Determine whether the user can change display order of roles.
     */
    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Role');
    }

    /**
     * Determine whether the user can assign roles.
     */
    public function assign(AuthUser $authUser): bool
    {
        return $authUser->can('Assign:Role');
    }
}
