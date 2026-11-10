<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;

/**
 * Authorization policy for ClassEnrollment operations.
 */
final class ClassEnrollmentPolicy
{
    use HandlesAuthorization;

    /**
     * Authorized administrative and student-services roles with enrollment mutation access.
     */
    private const array ADMIN_OR_REGISTRAR_ROLES = [
        UserRole::Developer,
        UserRole::SuperAdmin,
        UserRole::Admin,
        UserRole::Registrar,
        UserRole::AssistantRegistrar,
    ];

    /**
     * Determine whether the user can view any class enrollments.
     *
     * @param  AuthUser  $authUser  The authenticated user.
     * @return bool True if authorized, false otherwise.
     */
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ClassEnrollment')
            || ($authUser instanceof User && (
                $authUser->isAdministrative()
                || $authUser->isFaculty()
                || $authUser->isStudentRole()
                || in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true)
            ));
    }

    /**
     * Determine whether the user can view a specific class enrollment.
     *
     * @param  AuthUser  $authUser  The authenticated user.
     * @param  ClassEnrollment|null  $enrollment  The class enrollment record.
     * @return bool True if authorized, false otherwise.
     */
    public function view(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        if ($authUser->can('View:ClassEnrollment')
            || ($authUser instanceof User && ($authUser->isAdministrative() || in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true)))
        ) {
            return true;
        }

        if ($enrollment !== null && $authUser instanceof User) {
            if ($authUser->isFaculty()) {
                return $this->ownsClass($authUser, $enrollment->class);
            }

            if ($authUser->isStudentRole()) {
                return $this->ownsEnrollment($authUser, $enrollment);
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create class enrollments.
     *
     * @param  AuthUser  $authUser  The authenticated user.
     * @return bool True if authorized, false otherwise.
     */
    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ClassEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true));
    }

    /**
     * Determine whether the user can update a class enrollment.
     *
     * @param  AuthUser  $authUser  The authenticated user.
     * @param  ClassEnrollment|null  $enrollment  The class enrollment record.
     * @return bool True if authorized, false otherwise.
     */
    public function update(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        if ($authUser->can('Update:ClassEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true))
        ) {
            return true;
        }

        if ($enrollment !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $enrollment->class);
        }

        return false;
    }

    /**
     * Determine whether the user can delete a class enrollment.
     *
     * @param  AuthUser  $authUser  The authenticated user.
     * @param  ClassEnrollment|null  $enrollment  The class enrollment record.
     * @return bool True if authorized, false otherwise.
     */
    public function delete(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        if ($authUser->can('Delete:ClassEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true))
        ) {
            return true;
        }

        if ($enrollment !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $enrollment->class);
        }

        return false;
    }

    /**
     * Determine whether the user can restore a soft-deleted class enrollment.
     *
     * @param  AuthUser  $authUser  The authenticated user.
     * @param  ClassEnrollment|null  $enrollment  The class enrollment record.
     * @return bool True if authorized, false otherwise.
     */
    public function restore(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        return $authUser->can('Restore:ClassEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true));
    }

    /**
     * Determine whether the user can permanently delete a class enrollment.
     *
     * @param  AuthUser  $authUser  The authenticated user.
     * @param  ClassEnrollment|null  $enrollment  The class enrollment record.
     * @return bool True if authorized, false otherwise.
     */
    public function forceDelete(AuthUser $authUser, ?ClassEnrollment $enrollment = null): bool
    {
        return $authUser->can('ForceDelete:ClassEnrollment')
            || ($authUser instanceof User && in_array($authUser->role, [
                UserRole::Developer,
                UserRole::SuperAdmin,
                UserRole::Admin,
            ], true));
    }

    public function ownsEnrollment(User $user, ClassEnrollment $enrollment): bool
    {
        $student = $this->resolveAuthenticatedStudent($user);

        return $student instanceof Student && (int) $enrollment->student_id === (int) $student->id;
    }

    public function resolveAuthenticatedStudent(User $user): ?Student
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

    /**
     * Determine whether the given user is the assigned faculty for the class.
     *
     * @param  User  $user  The user to verify.
     * @param  Classes|null  $class  The class record.
     * @return bool True if user is the assigned faculty, false otherwise.
     */
    private function ownsClass(User $user, ?Classes $class): bool
    {
        if (! $class || $class->faculty_id === null || ! $user->role?->isFaculty()) {
            return false;
        }

        $faculty = $this->resolveAuthenticatedFaculty($user);

        return $faculty instanceof Faculty && (string) $class->faculty_id === (string) $faculty->id;
    }

    /**
     * Resolve the verified faculty member associated with the authenticated user.
     *
     * @param  User  $user  The user to resolve faculty profile for.
     * @return Faculty|null The verified faculty model or null.
     */
    private function resolveAuthenticatedFaculty(User $user): ?Faculty
    {
        if (filled($user->email)) {
            $faculty = Faculty::query()->where('email', $user->email)->first();
            if ($faculty instanceof Faculty) {
                return $faculty;
            }
        }

        if (filled($user->record_id)) {
            $faculty = Faculty::query()->find($user->record_id);
            if ($faculty instanceof Faculty && (empty($faculty->email) || strcasecmp((string) $faculty->email, (string) $user->email) === 0)) {
                return $faculty;
            }
        }

        if (filled($user->faculty_id_number)) {
            $faculty = Faculty::query()->where('faculty_id_number', $user->faculty_id_number)->first();
            if ($faculty instanceof Faculty && (empty($faculty->email) || strcasecmp((string) $faculty->email, (string) $user->email) === 0)) {
                return $faculty;
            }
        }

        return null;
    }
}
