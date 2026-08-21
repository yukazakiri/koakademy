<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\SchoolLevel;
use App\Features\Toggles\StudentAvatarUpload;
use App\Features\Toggles\StudentInformationUpdates;
use App\Features\Toggles\StudentSignaturePad;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\User;
use App\Services\AnalyticsSettingsService;
use App\Services\FacultyClassShareService;
use App\Services\GeneralSettingsService;
use App\Services\ModuleAdminNavigationService;
use App\Services\Newsletter\NewsletterSubscriptionService;
use App\Services\NotificationShareService;
use App\Services\OnboardingShareService;
use App\Services\SettingsShareService;
use App\Services\SocialiteProviderService;
use App\Services\StudentClassShareService;
use App\Support\AdministratorSidebarCounts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;
use Laravel\Pennant\Feature;
use Modules\Announcement\Services\AnnouncementDataService;
use Override;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    #[Override]
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
        $studentClassService = app(StudentClassShareService::class);
        $announcementService = app(AnnouncementDataService::class);
        $analyticsService = app(AnalyticsSettingsService::class);
        $moduleAdminNavigationService = app(ModuleAdminNavigationService::class);
        $administratorSidebarCounts = app(AdministratorSidebarCounts::class);
        $socialiteProviderService = app(SocialiteProviderService::class);
        $generalSettingsService = app(GeneralSettingsService::class);

        $featureValues = $onboardingService->getAllFeatureValues($user);
        $studentInformationUpdatesEnabled = $user && Feature::for($user)->active(StudentInformationUpdates::class);
        $libraryEnabled = (bool) $generalSettingsService->getGlobalSetting('library_module_enabled', false);

        return array_merge(
            parent::share($request),
            [
                'auth' => $settingsService->getAuthData($user),
                'socialMediaSettings' => $settingsService->getSocialMediaSettings(),
                'siteSettings' => $settingsService->getSiteSettings($request),
                'branding' => $settingsService->getBranding(),
                'seo' => $settingsService->getSeoSettings(...),
                'grading' => $settingsService->getGrading(),
                'analytics' => $analyticsService->getFrontendConfig(),
                'meta' => [
                    'appName' => $settingsService->getAppName($request),
                    'isPortalDomain' => $settingsService->isPortalDomain($request),
                ],
                'demoMode' => $this->getDemoModeData(),
                'status' => session('status'),
                'settings' => $settingsService->getSettings(),
                'socialAuthProviders' => $socialiteProviderService->enabledProviders(...),
                'version' => config('app.version'),
                'onboarding' => [
                    'forceOnLogin' => (bool) config('onboarding.force_on_login'),
                    'features' => $onboardingService->getOnboardingFeatures($user),
                    'dismissEndpoint' => route('onboarding.dismiss'),
                ],
                'featureFlags' => [
                    'experimentalKeys' => config('onboarding.experimental_feature_keys', []),
                    'enabledRoutes' => array_merge(
                        $onboardingService->getSidebarFeatureFlags($featureValues),
                        ['library' => $libraryEnabled]
                    ),
                    'library' => $libraryEnabled,
                    'studentSignaturePad' => $user && Feature::for($user)->active(StudentSignaturePad::class),
                    'studentAvatarUpload' => $user && Feature::for($user)->active(StudentAvatarUpload::class),
                    'studentInformationUpdates' => $studentInformationUpdatesEnabled,
                ],
                'facultyClasses' => $facultyClassService->getFacultyClasses($user),
                'studentClasses' => $studentClassService->getStudentClasses($user),
                'notifications' => $notificationService->transformNotifications($user),
                'unreadNotificationsCount' => $notificationService->getUnreadCount($user),
                'unresolvedHelpTicketsCount' => $onboardingService->getUnresolvedHelpTicketsCount($user),
                'newsletter' => fn (): array => $this->getNewsletterData($user),
                'adminSidebarCounts' => fn () => $administratorSidebarCounts->resolve($request),
                'moduleAdminRoutes' => $moduleAdminNavigationService->getRoutes(),
                'institutionOnboarding' => fn (): ?array => $this->getInstitutionOnboardingData($request, $user),
            ],
            [
                'announcements' => fn (): array => $this->getSharedAnnouncements(
                    request: $request,
                    user: $user,
                    announcementService: $announcementService,
                ),
            ]
        );
    }

    /**
     * Newsletter prompt state shared with the portal frontend. Only student
     * and faculty users are ever prompted; admins and guests are skipped
     * without touching the database or the active newsletter provider.
     *
     * @return array{enabled: bool, shouldPrompt: bool, feedback: array{type: string, message: string}|null}
     */
    private function getNewsletterData(?User $user): array
    {
        $feedback = session('newsletter_feedback');

        if (! $user instanceof User || (! $user->isStudentRole() && ! $user->isFaculty())) {
            return [
                'enabled' => false,
                'shouldPrompt' => false,
                'feedback' => $feedback,
            ];
        }

        $newsletter = app(NewsletterSubscriptionService::class);

        return [
            'enabled' => $newsletter->isEnabled(),
            'shouldPrompt' => $newsletter->shouldPromptUser($user),
            'feedback' => $feedback,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSharedAnnouncements(
        Request $request,
        ?User $user,
        AnnouncementDataService $announcementService,
    ): array {
        return $announcementService->getSharedBannerAnnouncements(
            user: $user,
            location: $this->resolveAnnouncementLocation($request),
        );
    }

    /**
     * @return array{needs_school_level: bool, school: array{id: int, name: string, code: string|null}|null, school_level_options: array<int, array{value: string, label: string, description: string}>, update_endpoint: string|null}|null
     */
    private function getInstitutionOnboardingData(Request $request, ?User $user): ?array
    {
        if (! $user instanceof User || ! $request->is('administrators/*')) {
            return null;
        }

        if (! $user->can('updateSchool', GeneralSetting::class)) {
            return null;
        }

        if (! Schema::hasTable('schools') || ! Schema::hasColumn('schools', 'school_level')) {
            return null;
        }

        $activeSchoolId = app(GeneralSettingsService::class)->getActiveSchoolId();

        if ($activeSchoolId === null) {
            return null;
        }

        $school = School::query()->find($activeSchoolId);

        if (! $school instanceof School) {
            return null;
        }

        $schoolLevel = $school->getRawOriginal('school_level');

        return [
            'needs_school_level' => blank($schoolLevel),
            'school' => [
                'id' => $school->id,
                'name' => $school->name,
                'code' => $school->code,
            ],
            'school_level_options' => SchoolLevel::optionsForFrontend(),
            'update_endpoint' => route('administrators.system-management.school-level.update'),
        ];
    }

    private function resolveAnnouncementLocation(Request $request): string
    {
        if ($request->routeIs('login')) {
            return 'login';
        }

        if ($request->routeIs('signup.*') || $request->is('signup')) {
            return 'signup';
        }

        if ($request->routeIs('enrollment.*') || $request->is('enrollment')) {
            return 'enrollment';
        }

        if ($request->is('student/*')) {
            return 'student_layout';
        }

        if ($request->is('faculty/*')) {
            return 'faculty_layout';
        }

        if ($request->is('administrators/*') || $request->is('admin/*')) {
            return 'admin_layout';
        }

        return 'home';
    }

    /**
     * @return array{enabled: bool, accounts: array<int, array{role: string, label: string, description: string}>}
     */
    private function getDemoModeData(): array
    {
        $accounts = collect(config('demo.accounts', []))
            ->map(static fn (array $account): array => [
                'role' => (string) $account['role'],
                'label' => (string) $account['label'],
                'description' => (string) $account['description'],
            ])
            ->values()
            ->all();

        return [
            'enabled' => app()->environment('demo'),
            'accounts' => $accounts,
        ];
    }
}
