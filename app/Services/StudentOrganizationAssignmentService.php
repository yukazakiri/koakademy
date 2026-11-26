<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentStatus;
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
            ->whereIn('student_type', array_column(StudentType::cases(), 'value'))
            ->whereIn('status', StudentStatus::registerableValues())
            ->whereNotNull('school_id')
            ->whereHas('school', fn ($query) => $query->where('is_active', true))
            ->with(['Course' => fn ($query) => $query->withoutSchoolScope()])
            ->orderBy('id')
            ->get();
    }

    /**
     * Detect universal signup hints for a set of lookup candidates.
     * Deliberately excludes internal database primary keys from unauthenticated responses.
     *
     * @param  Collection<int, Student>  $students
     * @return array<string, mixed>
     */
    public function signupHints(Collection $students): array
    {
        $student = $students->first();
        if (! $student instanceof Student) {
            $presentation = app(StudentConfigurationService::class)->signupPresentation();

            return [
                'student_type' => null,
                'identifier_type' => 'student_id',
                'identifier_label' => $presentation['universal_identifier_label'],
                'identifier_placeholder' => $presentation['student_identifier_placeholder'],
                'identifier_help_text' => $presentation['student_identifier_help_text'],
                'student_id_label' => $presentation['student_id_label'],
                'external_identifier_label' => $presentation['external_identifier_enabled'] ? $presentation['external_identifier_label'] : null,
                'accepts_external_identifier' => $presentation['external_identifier_enabled'],
                'allow_identifier_recovery' => $presentation['allow_identifier_recovery'],
                'require_password_on_social_signup' => $presentation['require_password_on_social_signup'],
                'multiple_types' => false,
            ];
        }

        $config = app(StudentConfigurationService::class);
        $schoolIds = $students->pluck('school_id')->filter()->unique()->values();
        $primarySchoolId = $schoolIds->count() === 1 ? (int) $schoolIds->first() : $student->school_id;
        $presentation = $config->signupPresentation($primarySchoolId);

        $expectedFields = $students
            ->map(function (Student $candidate) use ($config): string {
                $usesExternal = $config->usesExternal($candidate->student_type->value, $candidate->school_id)
                    && (string) $candidate->student_id === (string) $candidate->lrn;

                return $usesExternal ? 'lrn' : 'student_id';
            })
            ->unique()
            ->values();

        $distinctTypes = $students->pluck('student_type')->map(fn ($type) => $type instanceof StudentType ? $type->value : (string) $type)->unique()->values();
        $multipleTypes = $distinctTypes->count() > 1;

        // Check whether any matched candidate's school accepts an external identifier for signup.
        $acceptsExternal = $students->contains(function (Student $candidate) use ($config): bool {
            $external = $config->externalFor($candidate->student_type->value, $candidate->school_id);

            return (bool) ($external['enabled'] ?? false)
                && ((bool) ($external['allow_for_signup'] ?? true) || (bool) ($external['use_as_student_id'] ?? false));
        });

        $externalOnly = $expectedFields->count() === 1 && $expectedFields->first() === 'lrn';
        $studentIdLabel = (string) $presentation['student_id_label'];
        $externalLabel = (string) $presentation['external_identifier_label'];

        $identifierLabel = $externalOnly
            ? $externalLabel
            : ($acceptsExternal ? "{$studentIdLabel} or {$externalLabel}" : $studentIdLabel);

        $allowRecovery = $students->contains(
            fn (Student $candidate): bool => (bool) ($config->get($candidate->school_id)['signup']['allow_identifier_recovery'] ?? true),
        );

        $requirePasswordOnSocial = $students->contains(
            fn (Student $candidate): bool => (bool) ($config->get($candidate->school_id)['signup']['require_password_on_social_signup'] ?? false),
        );

        return [
            'student_type' => $multipleTypes ? null : $student->student_type->value,
            'identifier_type' => $externalOnly ? 'lrn' : 'student_id',
            'identifier_label' => $identifierLabel,
            'identifier_placeholder' => $presentation['student_identifier_placeholder'],
            'identifier_help_text' => $acceptsExternal && ! $externalOnly
                ? "Enter your {$studentIdLabel} or {$externalLabel} — we'll automatically match your student record."
                : $presentation['student_identifier_help_text'],
            'student_id_label' => $studentIdLabel,
            'external_identifier_label' => $acceptsExternal ? $externalLabel : null,
            'accepts_external_identifier' => $acceptsExternal,
            'allow_identifier_recovery' => $allowRecovery,
            'require_password_on_social_signup' => $requirePasswordOnSocial,
            'multiple_types' => $multipleTypes,
        ];
    }

    /**
     * Resolve one student identity across all organizations using a universal identifier
     * (primary Student ID or configured external/regional registry ID).
     *
     * @throws ValidationException
     */
    public function resolveForSignup(
        string $email,
        ?StudentType $studentType,
        string $identifier,
        int|string|null $recordId = null,
        bool $lockForUpdate = false,
        ?string $inputField = null,
    ): Student {
        $errorKey = $inputField ?? 'identifier';
        $normalizedIdentifier = mb_trim($identifier);
        $loweredIdentifier = mb_strtolower($normalizedIdentifier);

        $query = Student::withoutSchoolScope()
            ->whereRaw('LOWER(email) = ?', [$this->normalizeEmail($email)])
            ->whereIn('status', StudentStatus::registerableValues())
            ->where(function ($query) use ($normalizedIdentifier, $loweredIdentifier): void {
                $query->where('student_id', $normalizedIdentifier)
                    ->orWhereRaw('LOWER(TRIM(student_id)) = ?', [$loweredIdentifier])
                    ->orWhere('lrn', $normalizedIdentifier)
                    ->orWhereRaw('LOWER(TRIM(lrn)) = ?', [$loweredIdentifier]);
            })
            ->whereNotNull('school_id')
            ->whereHas('school', fn ($schoolQuery) => $schoolQuery->where('is_active', true));

        if ($studentType !== null) {
            $query->where('student_type', $studentType->value);
        }

        if (filled($recordId)) {
            $query->whereKey($recordId);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $config = app(StudentConfigurationService::class);
        $candidates = $query->limit(10)->get();

        // Policy belongs to each candidate school, not to a public caller's session.
        // Universal callers pass inputField='identifier' (or null) and match either primary student_id
        // or the school's enabled external registry identifier. Legacy callers passing student_id or lrn stay supported.
        $matchedByIdentifier = $candidates->filter(function (Student $student) use ($config, $loweredIdentifier, $inputField): bool {
            $studentIdMatches = filled($student->student_id)
                && mb_strtolower(mb_trim((string) $student->student_id)) === $loweredIdentifier;

            $externalConfig = $config->externalFor($student->student_type->value, $student->school_id);
            $externalAllowedForSignup = (bool) ($externalConfig['enabled'] ?? false)
                && ((bool) ($externalConfig['allow_for_signup'] ?? true) || (bool) ($externalConfig['use_as_student_id'] ?? false));

            $externalMatches = $externalAllowedForSignup
                && filled($student->lrn)
                && mb_strtolower(mb_trim((string) $student->lrn)) === $loweredIdentifier;

            return match ($inputField) {
                'student_id' => $studentIdMatches,
                'lrn' => $externalMatches || ($config->usesExternal($student->student_type->value, $student->school_id) && $studentIdMatches),
                default => $studentIdMatches || $externalMatches,
            };
        })->values();

        if ($matchedByIdentifier->isNotEmpty()) {
            $enabledByPolicy = $matchedByIdentifier->filter(
                fn (Student $student): bool => (bool) ($config->get($student->school_id)['signup']['allow_student_self_signup'] ?? true),
            )->values();

            if ($enabledByPolicy->isEmpty()) {
                throw ValidationException::withMessages([
                    'email' => ['Self-service student account activation is currently disabled for your institution. Please contact your school administrator.'],
                ]);
            }

            $matchedByIdentifier = $enabledByPolicy;
        }

        if ($matchedByIdentifier->count() !== 1) {
            throw ValidationException::withMessages([
                $errorKey => [$matchedByIdentifier->isEmpty()
                    ? 'These student details do not match an active school record.'
                    : 'These student details match more than one organization. Please contact your school administrator.'],
            ]);
        }

        $student = $matchedByIdentifier->firstOrFail();

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
