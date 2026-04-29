<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class ShsStrandPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ShsStrand');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:ShsStrand');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ShsStrand');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:ShsStrand');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:ShsStrand');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:ShsStrand');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:ShsStrand');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ShsStrand');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ShsStrand');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:ShsStrand');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ShsStrand');
    }
}
