<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class DepartmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Department');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Department');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Department');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Department');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Department');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Department');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Department');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Department');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Department');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Department');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Department');
    }
}
