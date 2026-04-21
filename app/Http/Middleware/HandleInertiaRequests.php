<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Features\StudentAvatarUpload;
use App\Features\StudentSignaturePad;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Services\AnalyticsSettingsService;
use App\Services\FacultyClassShareService;
use App\Services\GeneralSettingsService;
use App\Services\ModuleAdminNavigationService;
use App\Services\NotificationShareService;
use App\Services\OnboardingShareService;
use App\Services\SettingsShareService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use Laravel\Pennant\Feature;
use Modules\Announcement\Services\AnnouncementDataService;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $settingsService = app(SettingsShareService::class);
        $notificationService = app(NotificationShareService::class);
        $onboardingService = app(OnboardingShareService::class);
        $facultyClassService = app(FacultyClassShareService::class);
        $announcementService = app(AnnouncementDataService::class);
        $analyticsService = app(AnalyticsSettingsService::class);
        $moduleAdminNavigationService = app(ModuleAdminNavigationService::class);

        $featureValues = $onboardingService->getAllFeatureValues($user);

        return array_merge(
            parent::share($request),
            [
                'auth' => $settingsService->getAuthData($user),
                'socialMediaSettings' => $settingsService->getSocialMediaSettings(),
                'siteSettings' => $settingsService->getSiteSettings(),
                'branding' => $settingsService->getBranding(),
                'grading' => $settingsService->getGrading(),
                'analytics' => $analyticsService->getFrontendConfig(),
                'meta' => [
                    'appName' => $settingsService->getAppName($request),
                    'isPortalDomain' => $settingsService->isPortalDomain($request),
                ],
                'status' => session('status'),
                'settings' => $settingsService->getSettings(),
                'version' => config('app.version'),
                'onboarding' => [
                    'forceOnLogin' => (bool) config('onboarding.force_on_login'),
                    'features' => $onboardingService->getOnboardingFeatures($user),
                    'dismissEndpoint' => route('onboarding.dismiss'),
                ],
                'featureFlags' => [
                    'experimentalKeys' => config('onboarding.experimental_feature_keys', []),
                    'enabledRoutes' => $onboardingService->getSidebarFeatureFlags($featureValues),
                    'studentSignaturePad' => $user ? Feature::for($user)->active(StudentSignaturePad::class) : false,
                    'studentAvatarUpload' => $user ? Feature::for($user)->active(StudentAvatarUpload::class) : false,
                ],
                'facultyClasses' => $facultyClassService->getFacultyClasses($user),
                'notifications' => $notificationService->transformNotifications($user),
                'unreadNotificationsCount' => $notificationService->getUnreadCount($user),
                'unresolvedHelpTicketsCount' => $onboardingService->getUnresolvedHelpTicketsCount($user),
                'adminSidebarCounts' => $this->getAdminSidebarCounts($user),
                'moduleAdminRoutes' => $moduleAdminNavigationService->getRoutes(),
            ],
            [
                'announcements' => $announcementService->getSharedBannerAnnouncements(...),
            ]
        );
    }

    /**
     * @return array{students: int, enrollments: int, faculties: int, users: int}|null
     */
    private function getAdminSidebarCounts(?User $user): ?array
    {
        if (! $user || ! $user->canAccessAdminPortal()) {
            return null;
        }

        /** @var GeneralSettingsService $settingsService */
        $settingsService = app(GeneralSettingsService::class);
        $schoolYear = $settingsService->getCurrentSchoolYearString();
        $semester = $settingsService->getCurrentSemester();

        $tenantContext = app(\App\Services\TenantContext::class);
        $schoolId = $tenantContext->getCurrentSchoolId() ?? 'all';

        $cacheKey = sprintf('admin_sidebar_counts:%s:%s:%s', $schoolId, $schoolYear, $semester);

        return Cache::remember($cacheKey, 60, fn (): array => [
            'students' => Student::query()->count(),
            'enrollments' => StudentEnrollment::query()
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->count(),
            'faculties' => Faculty::query()->count(),
            'users' => User::query()->count(),
        ]);
    }
}
