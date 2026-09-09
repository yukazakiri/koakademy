<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class CodeAuthorityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CodeAuthority');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:CodeAuthority');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CodeAuthority');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:CodeAuthority');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:CodeAuthority');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:CodeAuthority');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:CodeAuthority');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CodeAuthority');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CodeAuthority');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:CodeAuthority');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CodeAuthority');
    }
}
