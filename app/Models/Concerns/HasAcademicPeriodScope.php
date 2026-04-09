<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Services\GeneralSettingsService;
use Illuminate\Database\Eloquent\Builder;

/**
 * HasAcademicPeriodScope Trait
 *
 * Provides consistent academic-period query scoping (school_year + semester)
 * across all models that participate in the enrollment cycle.
 *
 * Key design decisions:
 * - Uses `whereIn('school_year', [...])` with both the spaced ("2024 - 2025") and
 *   compact ("2024-2025") formats to handle the historical inconsistency in seeded
 *   and migrated data across the application.
 * - Resolves `GeneralSettingsService` via the service container so it honours the
 *   per-user school-year/semester override that the service already supports.
 * - Exposes `scopeForAcademicPeriod()` for explicit period queries (reports, comparisons)
 *   without coupling callers to the service directly.
 *
 * Models using this trait are expected to have `school_year` and `semester` columns
 * (or scope via a relationship — see `ClassEnrollment` which overrides the scope).
 *
 * @method static Builder<static> currentAcademicPeriod()
 * @method static Builder<static> forAcademicPeriod(string $schoolYear, int $semester)
 */
trait HasAcademicPeriodScope
{
    /**
     * Scope a query to the current academic period derived from GeneralSettingsService.
     * Tolerates both "2024 - 2025" and "2024-2025" school year formats.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentAcademicPeriod(Builder $query): Builder
    {
        /** @var GeneralSettingsService $service */
        $service = app(GeneralSettingsService::class);

        return $this->scopeForAcademicPeriod(
            $query,
            $service->getCurrentSchoolYearString(),
            $service->getCurrentSemester(),
        );
    }

    /**
     * Scope a query to an explicit academic period.
     * Accepts the school year in either format ("2024 - 2025" or "2024-2025").
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForAcademicPeriod(Builder $query, string $schoolYear, int $semester): Builder
    {
        $variants = $this->resolveSchoolYearVariants($schoolYear);

        return $query
            ->whereIn($this->getTable().'.school_year', $variants)
            ->where($this->getTable().'.semester', $semester);
    }

    /**
     * Return the current academic period as a structured array.
     *
     * @return array{school_year: string, semester: int, school_year_variants: array<string>}
     */
    public function getCurrentAcademicPeriod(): array
    {
        /** @var GeneralSettingsService $service */
        $service = app(GeneralSettingsService::class);
        $schoolYear = $service->getCurrentSchoolYearString();

        return [
            'school_year' => $schoolYear,
            'semester' => $service->getCurrentSemester(),
            'school_year_variants' => $this->resolveSchoolYearVariants($schoolYear),
        ];
    }

    /**
     * Resolve both the spaced and compact variants of a school year string.
     * e.g., "2024 - 2025" → ["2024 - 2025", "2024-2025"]
     *
     * @return array<string>
     */
    protected function resolveSchoolYearVariants(string $schoolYear): array
    {
        $normalized = GeneralSettingsService::normalizeSchoolYear($schoolYear);
        $compact = str_replace(' ', '', $normalized);

        return array_unique([$normalized, $compact]);
    }
}
