<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class IndustryCourseCodePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:IndustryCourseCode');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:IndustryCourseCode');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:IndustryCourseCode');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:IndustryCourseCode');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:IndustryCourseCode');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:IndustryCourseCode');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:IndustryCourseCode');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:IndustryCourseCode');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:IndustryCourseCode');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:IndustryCourseCode');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:IndustryCourseCode');
    }
}
