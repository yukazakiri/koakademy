<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\StudentEnrollment;
use Illuminate\Auth\Access\HandlesAuthorization;

class StudentEnrollmentPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StudentEnrollment');
    }

    public function view(AuthUser $authUser, StudentEnrollment $studentEnrollment): bool
    {
        return $authUser->can('View:StudentEnrollment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StudentEnrollment');
    }

    public function update(AuthUser $authUser, StudentEnrollment $studentEnrollment): bool
    {
        return $authUser->can('Update:StudentEnrollment');
    }

    public function delete(AuthUser $authUser, StudentEnrollment $studentEnrollment): bool
    {
        return $authUser->can('Delete:StudentEnrollment');
    }

    public function restore(AuthUser $authUser, StudentEnrollment $studentEnrollment): bool
    {
        return $authUser->can('Restore:StudentEnrollment');
    }

    public function forceDelete(AuthUser $authUser, StudentEnrollment $studentEnrollment): bool
    {
        return $authUser->can('ForceDelete:StudentEnrollment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:StudentEnrollment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:StudentEnrollment');
    }

    public function replicate(AuthUser $authUser, StudentEnrollment $studentEnrollment): bool
    {
        return $authUser->can('Replicate:StudentEnrollment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StudentEnrollment');
    }

}