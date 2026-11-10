<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;

final class StudentEnrollmentPolicy
{
    use HandlesAuthorization;

    private const array ADMIN_OR_REGISTRAR_ROLES = [
        UserRole::Developer,
        UserRole::SuperAdmin,
        UserRole::Admin,
        UserRole::Registrar,
        UserRole::AssistantRegistrar,
    ];

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:StudentEnrollment')
            || ($authUser instanceof User && ($authUser->isAdministrative() || $authUser->isStudentServices() || $authUser->isStudentRole()));
    }

    public function view(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        if ($authUser->can('View:StudentEnrollment')
            || ($authUser instanceof User && ($authUser->isAdministrative() || $authUser->isStudentServices() || in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true)))
        ) {
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

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:StudentEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true));
    }

    public function update(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        return $authUser->can('Update:StudentEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true));
    }

    public function delete(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        return $authUser->can('Delete:StudentEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true));
    }

    public function restore(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        return $authUser->can('Restore:StudentEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true));
    }

    public function forceDelete(AuthUser $authUser, ?StudentEnrollment $enrollment = null): bool
    {
        if ($enrollment?->hasFinancialRecords()) {
            return false;
        }

        return $authUser->can('ForceDelete:StudentEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, [
                UserRole::Developer,
                UserRole::SuperAdmin,
                UserRole::Admin,
            ], true));
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
