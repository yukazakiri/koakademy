<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Schema;

final class ClassPostPolicy
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
        return $authUser->can('ViewAny:ClassPost')
            || ($authUser instanceof User && (
                $authUser->isAdministrative()
                || $authUser->isFaculty()
                || $authUser->isStudentRole()
                || in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true)
            ));
    }

    public function view(AuthUser $authUser, ?ClassPost $post = null): bool
    {
        if ($authUser->can('View:ClassPost')
            || ($authUser instanceof User && ($authUser->isAdministrative() || in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true)))
        ) {
            return true;
        }

        if ($post !== null && $authUser instanceof User) {
            if ($authUser->isFaculty()) {
                return $this->ownsClass($authUser, $post->class);
            }

            if ($authUser->isStudentRole()) {
                return $this->isEnrolledInClass($authUser, (int) $post->class_id);
            }
        }

        return false;
    }

    public function create(AuthUser $authUser, ?Classes $class = null): bool
    {
        if ($authUser->can('Create:ClassPost')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true))
        ) {
            return true;
        }

        if ($class !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $class);
        }

        return false;
    }

    public function update(AuthUser $authUser, ?ClassPost $post = null): bool
    {
        if ($authUser->can('Update:ClassPost')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true))
        ) {
            return true;
        }

        if ($post !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $post->class);
        }

        return false;
    }

    public function delete(AuthUser $authUser, ?ClassPost $post = null): bool
    {
        if ($authUser->can('Delete:ClassPost')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true))
        ) {
            return true;
        }

        if ($post !== null && $authUser instanceof User && $authUser->isFaculty()) {
            return $this->ownsClass($authUser, $post->class);
        }

        return false;
    }

    public function restore(AuthUser $authUser, ?ClassPost $post = null): bool
    {
        return $authUser->can('Restore:ClassPost')
            || ($authUser instanceof User && in_array($authUser->role, self::ADMIN_OR_REGISTRAR_ROLES, true));
    }

    public function forceDelete(AuthUser $authUser, ?ClassPost $post = null): bool
    {
        return $authUser->can('ForceDelete:ClassPost')
            || ($authUser instanceof User && in_array($authUser->role, [
                UserRole::Developer,
                UserRole::SuperAdmin,
                UserRole::Admin,
            ], true));
    }

    public function ownsClass(User $user, ?Classes $class): bool
    {
        if (! $class || $class->faculty_id === null || ! $user->role?->isFaculty()) {
            return false;
        }

        $faculty = $this->resolveAuthenticatedFaculty($user);

        return $faculty instanceof Faculty && (string) $class->faculty_id === (string) $faculty->id;
    }

    public function isEnrolledInClass(User $user, int $classId): bool
    {
        $student = $this->resolveAuthenticatedStudent($user);

        if (! $student instanceof Student) {
            return false;
        }

        return ClassEnrollment::query()
            ->where('class_id', $classId)
            ->where('student_id', $student->id)
            ->exists();
    }

    public function resolveAuthenticatedFaculty(User $user): ?Faculty
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
}
