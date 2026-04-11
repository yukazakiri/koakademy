<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\School;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * TenantContext - Manages the current organization (school) context for multi-tenancy.
 *
 * This service provides a single source of truth for the current tenant (school)
 * throughout the request lifecycle. It supports:
 * - Session-based tenant persistence
 * - User default tenant fallback
 * - Super admin bypass for global access
 */
final class TenantContext
{
    private const string SESSION_KEY = 'current_school_id';

    private ?School $currentSchool = null;

    private bool $resolved = false;

    /**
     * Get the current school/organization for the tenant context.
     */
    public function getCurrentSchool(): ?School
    {
        if ($this->resolved) {
            return $this->currentSchool;
        }

        if (! Auth::hasUser() && ! Session::isStarted()) {
            return null;
        }

        $schoolId = Session::get(self::SESSION_KEY);

        if (! $schoolId) {
            try {
                $schoolId = app(GeneralSettingsService::class)->getActiveSchoolId();
            } catch (Exception) {
                // Ignore if service not available
            }
        }

        if ($schoolId) {
            $this->currentSchool = School::query()->find($schoolId);

            if ($this->currentSchool instanceof School) {
                $this->resolved = true;

                return $this->currentSchool;
            }
        }

        $user = Auth::user();

        if ($user && $user->school_id) {
            $this->currentSchool = $user->school;
            $this->setCurrentSchool($this->currentSchool);

            return $this->currentSchool;
        }

        $this->resolved = true;

        return $this->currentSchool;
    }

    /**
     * Get the current school ID for queries.
     */
    public function getCurrentSchoolId(): ?int
    {
        return $this->getCurrentSchool()?->id;
    }

    /**
     * Set the current school/organization context.
     */
    public function setCurrentSchool(?School $school): void
    {
        $this->currentSchool = $school;
        $this->resolved = true;

        if ($school instanceof School) {
            Session::put(self::SESSION_KEY, $school->id);
        } else {
            Session::forget(self::SESSION_KEY);
        }
    }

    /**
     * Set the current school by ID.
     */
    public function setCurrentSchoolId(?int $schoolId): void
    {
        if ($schoolId === null) {
            $this->setCurrentSchool(null);

            return;
        }

        $school = School::find($schoolId);
        $this->setCurrentSchool($school);
    }

    /**
     * Check if a tenant context is currently active.
     */
    public function hasCurrentSchool(): bool
    {
        return $this->getCurrentSchool() instanceof School;
    }

    /**
     * Check if the current user can access all organizations (super admin).
     */
    public function canAccessAllOrganizations(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return in_array($user->role->value ?? $user->role, [
            'developer',
            'admin',
            'super_admin',
        ], true);
    }

    /**
     * Check if the current user can access a specific organization.
     */
    public function canAccessOrganization(School|int $school): bool
    {
        // Super admins can access all
        if ($this->canAccessAllOrganizations()) {
            return true;
        }

        $schoolId = $school instanceof School ? $school->id : $school;
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Check if user belongs to this organization
        // or has membership through organization_user pivot
        if ($user->school_id === $schoolId) {
            return true;
        }

        // Check pivot table for multi-org membership
        return $user->organizations()->where('schools.id', $schoolId)->exists();
    }

    /**
     * Get all organizations the current user can access.
     * Results are cached per request to avoid duplicate queries.
     *
     * @return Collection<int, School>
     */
    public function getAccessibleOrganizations(): Collection
    {
        $cacheKey = 'accessible_organizations_'.Auth::id();

        return cache()->remember($cacheKey, 60, function () {
            $user = Auth::user();

            if (! $user) {
                return new Collection;
            }

            // Super admins can access all active organizations
            if ($this->canAccessAllOrganizations()) {
                return School::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }

            // Get organizations through membership
            $organizations = $user->organizations()
                ->wherePivot('is_active', true)
                ->orderBy('name')
                ->get();

            // Also include the user's primary organization if not already included
            if ($user->school_id && ! $organizations->contains('id', $user->school_id)) {
                $primarySchool = $user->school;

                if ($primarySchool && $primarySchool->is_active) {
                    $organizations->prepend($primarySchool);
                }
            }

            return $organizations;
        });
    }

    /**
     * Clear the current tenant context.
     */
    public function clear(): void
    {
        $this->currentSchool = null;
        $this->resolved = false;
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Reset the resolved state (useful for testing).
     */
    public function reset(): void
    {
        $this->resolved = false;
        $this->currentSchool = null;
    }
}
