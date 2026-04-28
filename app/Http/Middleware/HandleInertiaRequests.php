<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Features\StudentAvatarUpload;
use App\Features\StudentSignaturePad;
use App\Services\AnalyticsSettingsService;
use App\Services\FacultyClassShareService;
use App\Services\ModuleAdminNavigationService;
use App\Services\NotificationShareService;
use App\Services\OnboardingShareService;
use App\Services\SettingsShareService;
use App\Support\AdministratorSidebarCounts;
use Illuminate\Http\Request;
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
        $administratorSidebarCounts = app(AdministratorSidebarCounts::class);

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
                    'studentSignaturePad' => $user && Feature::for($user)->active(StudentSignaturePad::class),
                    'studentAvatarUpload' => $user && Feature::for($user)->active(StudentAvatarUpload::class),
                ],
                'facultyClasses' => $facultyClassService->getFacultyClasses($user),
                'notifications' => $notificationService->transformNotifications($user),
                'unreadNotificationsCount' => $notificationService->getUnreadCount($user),
                'unresolvedHelpTicketsCount' => $onboardingService->getUnresolvedHelpTicketsCount($user),
                'adminSidebarCounts' => fn () => $administratorSidebarCounts->resolve($request),
                'moduleAdminRoutes' => $moduleAdminNavigationService->getRoutes(),
            ],
            [
                'announcements' => $announcementService->getSharedBannerAnnouncements(...),
            ]
        );
    }
}
