<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

final class ClassEnrollmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClassEnrollment')
            || ($authUser instanceof User && ($authUser->isAdministrative() || $authUser->isFaculty()));
    }

    public function view(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        if ($authUser->can('View:ClassEnrollment') || ($authUser instanceof User && $authUser->isAdministrative())) {
            return true;
        }

        if ($enrollment !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $enrollment->class);
        }

        return false;
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClassEnrollment')
            || ($authUser instanceof User && $authUser->isAdministrative());
    }

    public function update(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        if ($authUser->can('Update:ClassEnrollment') || ($authUser instanceof User && $authUser->isAdministrative())) {
            return true;
        }

        if ($enrollment !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $enrollment->class);
        }

        return false;
    }

    public function delete(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        if ($authUser->can('Delete:ClassEnrollment') || ($authUser instanceof User && $authUser->isAdministrative())) {
            return true;
        }

        if ($enrollment !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $enrollment->class);
        }

        return false;
    }

    public function restore(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        return $authUser->can('Restore:ClassEnrollment')
            || ($authUser instanceof User && $authUser->isAdministrative());
    }

    public function forceDelete(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        return $authUser->can('ForceDelete:ClassEnrollment')
            || ($authUser instanceof User && $authUser->isAdministrative());
    }

    private function ownsClass(User $user, ?Classes $class): bool
    {
        if (! $class || $class->faculty_id === null) {
            return false;
        }

        if (filled($user->record_id) && (string) $class->faculty_id === (string) $user->record_id) {
            return true;
        }

        if (filled($user->faculty_id_number)) {
            $faculty = Faculty::query()->where('faculty_id_number', $user->faculty_id_number)->first();
            if ($faculty && (string) $class->faculty_id === (string) $faculty->id) {
                return true;
            }
        }

        if (filled($user->email)) {
            $faculty = Faculty::query()->where('email', $user->email)->first();
            if ($faculty && (string) $class->faculty_id === (string) $faculty->id) {
                return true;
            }
        }

        return false;
    }
}
