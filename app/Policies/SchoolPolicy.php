<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\School;
use Illuminate\Auth\Access\HandlesAuthorization;

class SchoolPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:School');
    }

    public function view(AuthUser $authUser, School $school): bool
    {
        return $authUser->can('View:School');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:School');
    }

    public function update(AuthUser $authUser, School $school): bool
    {
        return $authUser->can('Update:School');
    }

    public function delete(AuthUser $authUser, School $school): bool
    {
        return $authUser->can('Delete:School');
    }

    public function restore(AuthUser $authUser, School $school): bool
    {
        return $authUser->can('Restore:School');
    }

    public function forceDelete(AuthUser $authUser, School $school): bool
    {
        return $authUser->can('ForceDelete:School');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:School');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:School');
    }

    public function replicate(AuthUser $authUser, School $school): bool
    {
        return $authUser->can('Replicate:School');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:School');
    }

}