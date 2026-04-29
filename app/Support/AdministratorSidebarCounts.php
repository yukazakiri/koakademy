<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\TenantContext;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;

final readonly class AdministratorSidebarCounts
{
    public function __construct(
        private GeneralSettingsService $settingsService,
        private TenantContext $tenantContext,
        private CacheRepository $cache,
    ) {}

    /**
     * @return array{students: int, enrollments: int, faculties: int, users: int}|null
     */
    public function resolve(Request $request): ?array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->canAccessAdminPortal()) {
            return null;
        }

        $counts = $this->resolveCachedNonStudentCounts();

        if ($request->attributes->has('admin_students_global_total')) {
            $counts['students'] = (int) $request->attributes->get('admin_students_global_total');
        } else {
            $counts['students'] = $this->resolveCachedStudentCount();
        }

        return $counts;
    }

    /**
     * @return array{enrollments: int, faculties: int, users: int}
     */
    private function resolveCachedNonStudentCounts(): array
    {
        return $this->cache->remember(
            $this->getCacheKey(),
            60,
            fn (): array => [
                'enrollments' => StudentEnrollment::query()
                    ->where('school_year', $this->settingsService->getCurrentSchoolYearString())
                    ->where('semester', $this->settingsService->getCurrentSemester())
                    ->count(),
                'faculties' => Faculty::query()->count(),
                'users' => User::query()->count(),
            ],
        );
    }

    private function resolveCachedStudentCount(): int
    {
        return (int) $this->cache->remember(
            $this->getStudentCacheKey(),
            60,
            fn (): int => Student::query()->count(),
        );
    }

    private function getCacheKey(): string
    {
        $schoolYear = $this->settingsService->getCurrentSchoolYearString();
        $semester = $this->settingsService->getCurrentSemester();
        $schoolId = $this->tenantContext->getCurrentSchoolId() ?? 'all';

        return sprintf('admin_sidebar_counts:%s:%s:%s', $schoolId, $schoolYear, $semester);
    }

    private function getStudentCacheKey(): string
    {
        return $this->getCacheKey().':students';
    }
}
