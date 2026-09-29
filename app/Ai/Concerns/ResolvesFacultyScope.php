<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

use App\Models\Faculty;
use App\Models\User;

/**
 * Resolves the faculty record that an authenticated user should be scoped to.
 *
 * Users are linked to their faculty profile through the `record_id` column on
 * the users table, which stores the primary key of the matching faculty row.
 * Administrative roles are not faculty scoped and retain oversight of every
 * class, matching the tools they are registered on.
 */
trait ResolvesFacultyScope
{
    /**
     * Determine whether the user may access class records beyond their own assignments.
     */
    private function hasFullClassVisibility(User $user): bool
    {
        return $user->isAdministrative();
    }

    /**
     * Resolve the faculty profile backing the authenticated user, if any.
     */
    private function resolveFacultyScope(User $user): ?Faculty
    {
        if (filled($user->record_id)) {
            $faculty = Faculty::query()->find($user->record_id);

            if ($faculty instanceof Faculty) {
                return $faculty;
            }
        }

        if (filled($user->faculty_id_number)) {
            return Faculty::query()
                ->where('faculty_id_number', $user->faculty_id_number)
                ->first();
        }

        return null;
    }

    /**
     * Determine whether the user is scoped to a specific faculty member's classes.
     */
    private function resolveScopedFaculty(User $user): ?Faculty
    {
        return $this->hasFullClassVisibility($user) ? null : $this->resolveFacultyScope($user);
    }
}
