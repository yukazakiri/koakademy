<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ShsStudent;
use Illuminate\Auth\Access\HandlesAuthorization;

class ShsStudentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShsStudent');
    }

    public function view(AuthUser $authUser, ShsStudent $shsStudent): bool
    {
        return $authUser->can('View:ShsStudent');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShsStudent');
    }

    public function update(AuthUser $authUser, ShsStudent $shsStudent): bool
    {
        return $authUser->can('Update:ShsStudent');
    }

    public function delete(AuthUser $authUser, ShsStudent $shsStudent): bool
    {
        return $authUser->can('Delete:ShsStudent');
    }

    public function restore(AuthUser $authUser, ShsStudent $shsStudent): bool
    {
        return $authUser->can('Restore:ShsStudent');
    }

    public function forceDelete(AuthUser $authUser, ShsStudent $shsStudent): bool
    {
        return $authUser->can('ForceDelete:ShsStudent');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ShsStudent');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ShsStudent');
    }

    public function replicate(AuthUser $authUser, ShsStudent $shsStudent): bool
    {
        return $authUser->can('Replicate:ShsStudent');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ShsStudent');
    }

}