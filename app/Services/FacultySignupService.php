<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\User;
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
     * Find the faculty record for an email, or null when the roster has no match.
     */
    public function findByEmail(string $email): ?Faculty
    {
        return Faculty::withoutSchoolScope()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(mb_trim($email))])
            ->first();
    }

    /**
     * Resolve the faculty record that matches the registering email.
     *
     * The email is unique across the roster, so at most one record can match. When a
     * faculty ID number is supplied it has to corroborate that record rather than
     * select a different one. A record the school stood down does not entitle its
     * holder to an account, so it never resolves.
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
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail]);

        if (filled($facultyIdNumber)) {
            $query->where('faculty_id_number', $facultyIdNumber);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $faculty = $query->first();

        if (! $faculty instanceof Faculty || ! $faculty->isRegisterable()) {
            throw ValidationException::withMessages([
                filled($facultyIdNumber) ? 'faculty_id_number' : 'email' => [filled($facultyIdNumber)
                    ? 'The faculty ID number does not match our records for this email address.'
                    : 'No active faculty record matches this email. Please contact the registrar.'],
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
