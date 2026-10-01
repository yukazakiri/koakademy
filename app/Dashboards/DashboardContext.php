<?php

declare(strict_types=1);

namespace App\Dashboards;

use App\Models\Department;
use App\Models\School;
use Carbon\CarbonImmutable;

/**
 * The tenant, academic period and range a desk payload is computed for.
 *
 * Every desk reads its scope from here rather than reaching for the session or the request, so
 * a desk is deterministic for a given context and trivially cacheable. Built once per request
 * by DashboardContext::for().
 */
final readonly class DashboardContext
{
    public function __construct(
        public ?School $school,
        public string $schoolYear,
        public int $semester,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?Department $department = null,
    ) {}

    /**
     * Resolve the current scope from the active tenant and academic period.
     *
     * `range` selects the trend window: `year` covers the school year, `months` the trailing
     * six months. Defaults to `year` so a desk renders sensibly with no query string.
     */
    public static function for(?string $range = null, ?Department $department = null): self
    {
        $settings = app(\App\Services\GeneralSettingsService::class);
        $tenants = app(\App\Services\TenantContext::class);

        $schoolYear = $settings->getCurrentSchoolYearString();
        $semester = $settings->getCurrentSemester();
        $school = $tenants->getCurrentSchool();

        [$from, $to] = self::resolveRange($range, $schoolYear, $semester);

        return new self($school, $schoolYear, $semester, $from, $to, $department);
    }

    /** Human-readable period label, e.g. "SY 2025-2026, Semester 1". */
    public function periodLabel(): string
    {
        return sprintf('SY %s, Semester %d', $this->schoolYear, $this->semester);
    }

    /** Trend window label matching the active range. */
    public function rangeLabel(): string
    {
        return $this->from->format('M Y').' - '.$this->to->format('M Y');
    }

    /**
     * Cache-key fragment. School-aware so a school's desk payloads never leak into another's,
     * matching AdministratorSidebarCounts' key shape.
     */
    public function cacheKey(): string
    {
        return sprintf(
            '%s:%s:sem%d:%s:%s',
            $this->school?->id ?? 'all',
            $this->schoolYear,
            $this->semester,
            $this->from->format('Ymd'),
            $this->to->format('Ymd'),
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private static function resolveRange(?string $range, string $schoolYear, int $semester): array
    {
        $to = CarbonImmutable::now()->endOfDay();

        if ($range === 'months') {
            return [CarbonImmutable::now()->subMonths(6)->startOfDay(), $to];
        }

        // Default to the school year when its bounds are resolvable, else the trailing year.
        $start = self::schoolYearStart($schoolYear);

        return $start instanceof CarbonImmutable ? [$start, $to] : [CarbonImmutable::now()->subYear()->startOfDay(), $to];
    }

    private static function schoolYearStart(string $schoolYear): ?CarbonImmutable
    {
        // "2025-2026" -> 2025-01-01. Malformed values fall back rather than throw.
        if (! str_contains($schoolYear, '-')) {
            return null;
        }

        $firstYear = explode('-', $schoolYear)[0];

        if (! ctype_digit($firstYear)) {
            return null;
        }

        return CarbonImmutable::createFromDate((int) $firstYear, 1, 1)->startOfDay();
    }
}
