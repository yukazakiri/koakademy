<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Resolves the institutional record that backs a faculty self-registration.
 *
 * A faculty account is only ever created from a row in the institutional roster, and the
 * role is derived from that row. This mirrors the student path, where the account is
 * bound to a real enrollment row, and closes the gap where a registrant could pick their
 * own rank without holding any institutional record.
 */
final class FacultySignupService
{
    /**
     * Find all faculty records for an email across active organizations (or unscoped legacy rows).
     *
     * @return Collection<int, Faculty>
     */
    public function candidatesForEmail(string $email): Collection
    {
        return Faculty::withoutSchoolScope()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim($email))])
            ->where(function ($query): void {
                $query->whereNull('school_id')
                    ->orWhereHas('school', fn ($schoolQuery) => $schoolQuery->where('is_active', true));
            })
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Find the best faculty record for an email, preferring registerable rows across active schools.
     */
    public function findByEmail(string $email): ?Faculty
    {
        $candidates = $this->candidatesForEmail($email);

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->first(fn (Faculty $faculty): bool => $faculty->isRegisterable())
            ?? $candidates->first();
    }

    /**
     * Build dynamic, privacy-safe signup hints for matched faculty roster rows.
     *
     * @param  Collection<int, Faculty>  $faculties
     * @return array<string, mixed>
     */
    public function signupHints(Collection $faculties): array
    {
        $config = app(StudentConfigurationService::class);
        $first = $faculties->first();
        $schoolId = $first instanceof Faculty ? $first->school_id : null;
        $presentation = $config->signupPresentation($schoolId);

        $policyRequiresFacultyId = $faculties->contains(
            fn (Faculty $candidate): bool => (bool) ($config->get($candidate->school_id)['signup']['require_faculty_id'] ?? false),
        );

        return [
            'requires_faculty_id' => $faculties->count() > 1 || $policyRequiresFacultyId,
            'has_faculty_id' => $faculties->contains(fn (Faculty $candidate): bool => filled($candidate->faculty_id_number)),
            'faculty_id_label' => $presentation['faculty_id_label'],
            'faculty_id_placeholder' => $presentation['faculty_identifier_placeholder'],
            'faculty_id_help_text' => $presentation['faculty_identifier_help_text'],
        ];
    }

    /**
     * Resolve the faculty record that matches the registering email.
     *
     * Faculty emails are unique per school, so multiple organizations can share an email.
     * When more than one active faculty record exists for an email, or the school requires
     * faculty ID verification, a faculty ID number is required.
     *
     * @throws ValidationException
     */
    public function resolveForSignup(
        string $email,
        ?string $facultyIdNumber = null,
        bool $lockForUpdate = false,
    ): Faculty {
        $normalizedEmail = mb_strtolower(mb_trim($email));

        $query = Faculty::withoutSchoolScope()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->where(function ($schoolScope): void {
                $schoolScope->whereNull('school_id')
                    ->orWhereHas('school', fn ($schoolQuery) => $schoolQuery->where('is_active', true));
            });

        if (filled($facultyIdNumber)) {
            $query->whereRaw('LOWER(faculty_id_number) = ?', [mb_strtolower(mb_trim((string) $facultyIdNumber))]);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $matches = $query->limit(10)->get()
            ->filter(fn (Faculty $faculty): bool => $faculty->isRegisterable())
            ->values();

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages([
                filled($facultyIdNumber) ? 'faculty_id_number' : 'email' => [filled($facultyIdNumber)
                    ? 'The faculty ID number does not match our records for this email address.'
                    : 'No active faculty record matches this email. Please contact the registrar.'],
            ]);
        }

        $config = app(StudentConfigurationService::class);
        $allowedMatches = $matches->filter(
            fn (Faculty $faculty): bool => (bool) ($config->get($faculty->school_id)['signup']['allow_faculty_self_signup'] ?? true),
        )->values();

        if ($allowedMatches->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => ['Self-service faculty account activation is currently disabled for your institution. Please contact your school administrator.'],
            ]);
        }

        $matches = $allowedMatches;

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'faculty_id_number' => [filled($facultyIdNumber)
                    ? 'This faculty email and ID match more than one organization. Please contact your school administrator.'
                    : 'This email matches faculty records in multiple organizations. Enter your Faculty ID Number to continue.'],
            ]);
        }

        $faculty = $matches->firstOrFail();

        $requireFacultyId = (bool) ($config->get($faculty->school_id)['signup']['require_faculty_id'] ?? false);
        if ($requireFacultyId && ! filled($facultyIdNumber)) {
            $label = (string) ($config->signupPresentation($faculty->school_id)['faculty_id_label'] ?? 'Faculty ID');
            throw ValidationException::withMessages([
                'faculty_id_number' => ["Your institution requires your {$label} to complete activation."],
            ]);
        }

        if (User::query()->where('record_id', (string) $faculty->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This faculty record is already linked to a portal account. Please sign in instead.'],
            ]);
        }

        return $faculty;
    }

    /**
     * The role the matched record grants, never the one the request asked for.
     */
    public function roleFor(Faculty $faculty): UserRole
    {
        return $faculty->positionToUserRole();
    }

    /**
     * Point a newly created account at the faculty record and the organization it belongs to.
     */
    public function assign(User $user, Faculty $faculty): void
    {
        $user->forceFill([
            'faculty_id_number' => $faculty->faculty_id_number,
            'record_id' => $faculty->id,
            'school_id' => $faculty->school_id,
            'department' => $faculty->department,
        ])->save();

        if ($faculty->school_id === null) {
            return;
        }

        $user->addToOrganization($faculty->school_id, [
            'role' => $user->role?->value,
            'is_primary' => true,
            'is_active' => true,
        ]);
    }
}
