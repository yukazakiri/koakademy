<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Account');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Account');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Account');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Account');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Account');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Account');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Account');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Account');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Account');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Account');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Account');
    }

}