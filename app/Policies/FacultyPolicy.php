<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class FacultyPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Faculty');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Faculty');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Faculty');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Faculty');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Faculty');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Faculty');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Faculty');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Faculty');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Faculty');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Faculty');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Faculty');
    }

}