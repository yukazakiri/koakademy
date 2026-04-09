<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class SubjectPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Subject');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:Subject');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Subject');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:Subject');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:Subject');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:Subject');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:Subject');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Subject');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Subject');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:Subject');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Subject');
    }
}
