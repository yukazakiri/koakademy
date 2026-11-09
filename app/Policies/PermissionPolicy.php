<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class PermissionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any permissions.
     */
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Permission');
    }

    /**
     * Determine whether the user can view the permission.
     */
    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Permission');
    }

    /**
     * Determine whether the user can create permissions.
     */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Permission');
    }

    /**
     * Determine whether the user can update the permission.
     */
    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Permission');
    }

    /**
     * Determine whether the user can delete the permission.
     */
    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Permission');
    }

    /**
     * Determine whether the user can restore the permission.
     */
    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Permission');
    }

    /**
     * Determine whether the user can permanently delete the permission.
     */
    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Permission');
    }

    /**
     * Determine whether the user can permanently delete any permissions.
     */
    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Permission');
    }

    /**
     * Determine whether the user can restore any permissions.
     */
    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Permission');
    }

    /**
     * Determine whether the user can duplicate permissions.
     */
    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Permission');
    }

    /**
     * Determine whether the user can change display order of permissions.
     */
    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Permission');
    }
}
