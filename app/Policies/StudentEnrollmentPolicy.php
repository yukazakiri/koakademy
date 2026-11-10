<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;

final class StudentEnrollmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        if ($authUser->can('ViewAny:StudentEnrollment')) {
            return true;
        }

        if ($authUser instanceof User && $authUser->isStudentRole()) {
            return true;
        }

        return false;
    }

    public function view(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        if ($authUser->can('View:StudentEnrollment')) {
            return true;
        }

        if ($enrollment !== null && $authUser instanceof User && $authUser->isStudentRole()) {
            return $this->ownsEnrollment($authUser, $enrollment);
        }

        return false;
    }

    public function exportDetailed(AuthUser $authUser): bool
    {
        return $this->view($authUser);
    }

    public function create(AuthUser $authUser, ?int $studentId = null): bool
    {
        if ($authUser->can('Create:StudentEnrollment')
            || $authUser->can('Update:StudentEnrollment')
        ) {
            return true;
        }

        if ($studentId !== null && $authUser instanceof User && $authUser->isStudentRole()) {
            $student = $this->resolveStudentForUser($authUser);

            return $student instanceof Student && (int) $student->id === $studentId;
        }

        return false;
    }

    public function update(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        return $authUser->can('Update:StudentEnrollment');
    }

    public function delete(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        return $authUser->can('Delete:StudentEnrollment');
    }

    public function restore(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        return $authUser->can('Restore:StudentEnrollment');
    }

    public function forceDelete(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        if ($enrollment?->hasFinancialRecords()) {
            return false;
        }

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

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:StudentEnrollment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:StudentEnrollment');
    }

    public function ownsEnrollment(User $user, StudentEnrollment $enrollment): bool
    {
        $student = $this->resolveStudentForUser($user);

        return $student instanceof Student && (int) $enrollment->student_id === (int) $student->id;
    }

    public function resolveStudentForUser(User $user): ?Student
    {
        if (filled($user->record_id)) {
            $student = Student::query()->find($user->record_id);
            if ($student instanceof Student && (empty($student->email) || strcasecmp((string) $student->email, (string) $user->email) === 0)) {
                return $student;
            }
        }

        if (Schema::hasColumn('students', 'user_id')) {
            $student = Student::query()->where('user_id', $user->id)->first();
            if ($student instanceof Student) {
                return $student;
            }
        }

        if (filled($user->email)) {
            $student = Student::query()->where('email', $user->email)->first();
            if ($student instanceof Student) {
                return $student;
            }
        }

        return null;
    }
}
