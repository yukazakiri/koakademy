<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentOrganizationAssignmentService
{
    /**
     * Find active student records across all organizations for public signup lookup.
     *
     * @return Collection<int, Student>
     */
    public function candidatesForEmail(string $email): Collection
    {
        return Student::withoutSchoolScope()
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->whereIn('student_type', [
                StudentType::College->value,
                StudentType::SeniorHighSchool->value,
            ])
            ->whereNotNull('school_id')
            ->whereHas('school', fn ($query) => $query->where('is_active', true))
            ->with(['Course' => fn ($query) => $query->withoutSchoolScope()])
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve one student identity across all organizations.
     *
     * @throws ValidationException
     */
    public function resolveForSignup(
        string $email,
        StudentType $studentType,
        string $identifier,
        int|string|null $recordId = null,
        bool $lockForUpdate = false,
    ): Student {
        $identifierField = $studentType->requiresLrn() ? 'lrn' : 'student_id';

        $query = Student::withoutSchoolScope()
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->where('student_type', $studentType->value)
            ->where($identifierField, $identifier)
            ->whereNotNull('school_id')
            ->whereHas('school', fn ($schoolQuery) => $schoolQuery->where('is_active', true));

        if (filled($recordId)) {
            $query->whereKey($recordId);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $students = $query->limit(2)->get();

        if ($students->count() !== 1) {
            throw ValidationException::withMessages([
                $identifierField => [$students->isEmpty()
                    ? 'These student details do not match an active school record.'
                    : 'These student details match more than one organization. Please contact your school administrator.'],
            ]);
        }

        $student = $students->firstOrFail();

        if ($student->user_id !== null) {
            throw ValidationException::withMessages([
                'email' => ['This student record is already linked to an account. Please sign in instead.'],
            ]);
        }

        return $student;
    }

    /**
     * Link a newly-created user to the organization owned by the matched student record.
     *
     * @throws ValidationException
     */
    public function assign(User $user, Student $student): void
    {
        if ($student->school_id === null) {
            throw ValidationException::withMessages([
                'email' => ['This student record is not assigned to an organization.'],
            ]);
        }

        if ($student->user_id !== null && (int) $student->user_id !== (int) $user->id) {
            throw ValidationException::withMessages([
                'email' => ['This student record is already linked to an account. Please sign in instead.'],
            ]);
        }

        $user->forceFill([
            'school_id' => $student->school_id,
            'record_id' => $student->id,
        ])->save();

        DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('school_id', '!=', $student->school_id)
            ->update(['is_primary' => false]);

        $user->addToOrganization($student->school_id, [
            'role' => $user->role?->value,
            'is_primary' => true,
            'is_active' => true,
        ]);

        $student->forceFill(['user_id' => $user->id])->save();
    }

    /**
     * Repair old student accounts only when an existing student-user link proves ownership.
     */
    public function reconcileExistingStudent(User $user): void
    {
        if (! $user->role?->isStudent() || $user->school_id !== null) {
            return;
        }

        DB::transaction(function () use ($user): void {
            $students = Student::withoutSchoolScope()
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id);

                    if ($user->record_id !== null) {
                        $query->orWhere('students.id', $user->record_id);
                    }
                })
                ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($user->email)])
                ->whereNotNull('school_id')
                ->whereHas('school', fn ($schoolQuery) => $schoolQuery->where('is_active', true))
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($students->count() !== 1) {
                return;
            }

            $student = $students->firstOrFail();

            if ($student->user_id !== null && (int) $student->user_id !== (int) $user->id) {
                return;
            }

            $this->assign($user, $student);
        });
    }

    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(mb_trim($email));
    }
}
