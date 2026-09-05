<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AssessmentFormPdfRenderer;
use App\Features\Toggles\AdminDeveloperMode;
use App\Features\Toggles\FacultyActionCenter;
use App\Features\Toggles\FacultyAnnouncements;
use App\Features\Toggles\FacultyAssessments;
use App\Features\Toggles\FacultyAtRiskAlerts;
use App\Features\Toggles\FacultyAttendance;
use App\Features\Toggles\FacultyClasses;
use App\Features\Toggles\FacultyDashboard;
use App\Features\Toggles\FacultyDeveloperMode;
use App\Features\Toggles\FacultyForms;
use App\Features\Toggles\FacultyGrades;
use App\Features\Toggles\FacultyHelp;
use App\Features\Toggles\FacultyInbox;
use App\Features\Toggles\FacultyInsights;
use App\Features\Toggles\FacultyOfficeHours;
use App\Features\Toggles\FacultyRequestsApprovals;
use App\Features\Toggles\FacultyResources;
use App\Features\Toggles\FacultySchedule;
use App\Features\Toggles\FacultySettings;
use App\Features\Toggles\FacultyToolkit;
use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Features\Toggles\OnlineTesdaEnrollment;
use App\Features\Toggles\StudentAnnouncements;
use App\Features\Toggles\StudentAttendanceTracker;
use App\Features\Toggles\StudentAvatarUpload;
use App\Features\Toggles\StudentClasses;
use App\Features\Toggles\StudentDashboard;
use App\Features\Toggles\StudentDeveloperMode;
use App\Features\Toggles\StudentGradesPreview;
use App\Features\Toggles\StudentHelp;
use App\Features\Toggles\StudentInformationUpdates;
use App\Features\Toggles\StudentSchedule;
use App\Features\Toggles\StudentSettings;
use App\Features\Toggles\StudentSignaturePad;
use App\Features\Toggles\StudentTuition;
use App\Filament\Handlers\ExportFailureHandler;
use App\Filament\Plugins\Widgets\PennantFeatureAdoptionWidget;
use App\Models\Passkey;
use App\Models\StudentTransaction;
use App\Models\User;
use App\Modules\CompatibilityChecker;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use App\Modules\RegistryClient;
use App\Modules\VersionConstraint;
use App\Observers\StudentTransactionObserver;
use App\Services\ChangelogService;
use App\Services\GeneralSettingsService;
use App\Services\LaravelAssessmentFormPdfRenderer;
use App\Services\Newsletter\NewsletterSettingsService;
use App\Services\Newsletter\NewsletterSubscriptionService;
use App\Services\VersionService;
use App\Support\HostingSecurity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;
use Laravel\Passkeys\Passkeys;
use Laravel\Pennant\Feature;
use Livewire\Livewire;
use Nwidart\Modules\Contracts\RepositoryInterface;
use Throwable;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExportFailureHandler::class);
        $this->app->singleton(ModuleManifestRepository::class);
        $this->app->singleton(ModuleStateRepository::class);
        $this->app->singleton(RegistryClient::class);
        $this->app->singleton(VersionConstraint::class);
        $this->app->singleton(CompatibilityChecker::class);

        $this->app->resolving(Migrator::class, function (Migrator $migrator): void {
            $migrationPath = (string) config('modules.paths.generator.migration.path');

            foreach ($this->app->make(RepositoryInterface::class)->all() as $module) {
                $path = $module->getExtraPath($migrationPath);

                if (is_dir($path)) {
                    $migrator->path($path);
                }
            }
        });

        // scoped = one instance per HTTP request, so Auth is always available when first used
        $this->app->scoped(GeneralSettingsService::class);
        $this->app->scoped(NewsletterSettingsService::class);
        $this->app->scoped(NewsletterSubscriptionService::class);
        $this->app->scoped(\App\Services\SentrySettingsService::class);
        $this->app->scoped(\App\Services\ErrorReportingService::class);
        $this->app->scoped(\App\Services\TenantContext::class);
        $this->app->bind(AssessmentFormPdfRenderer::class, LaravelAssessmentFormPdfRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.rate_limit', 60))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('api-login', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.login_rate_limit', 5))
                ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip());
        });

        RateLimiter::for('api-otp', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.otp_rate_limit', 5))
                ->by((string) $request->input('challenge_id', $request->ip()));
        });

        Model::unguard();
        StudentTransaction::observe(StudentTransactionObserver::class);

        Passkeys::useUserModel(User::class);
        Passkeys::usePasskeyModel(Passkey::class);

        if (app()->environment('production') && HostingSecurity::usesHttps((string) config('app.url'))) {
            URL::forceScheme('https');
        }

        Gate::define('viewApiDocs', fn (User $user): bool => $user->hasRole('super_admin'));

        Livewire::component(
            'daacreators.pennant-manager.widgets.feature-adoption-widget',
            PennantFeatureAdoptionWidget::class,
        );

        $this->definePennantFeatures();

        // Dynamically populate the Filament Feature Showcase config from
        // GitHub releases (stable only) and version.json so the changelog
        // stays in sync without manual config edits.
        $this->syncFeatureShowcaseConfig();

        $this->app->booted(function (): void {
            $this->removeMissingViewFinderPaths();
            $this->applySentrySettings();
        });
    }

    private function definePennantFeatures(): void
    {
        Feature::define(FacultyDashboard::class);
        Feature::define(FacultyActionCenter::class);
        Feature::define(FacultyClasses::class);
        Feature::define(FacultySchedule::class);
        Feature::define(FacultyAnnouncements::class);
        Feature::define(FacultySettings::class);
        Feature::define(FacultyHelp::class);
        Feature::define(FacultyToolkit::class);
        Feature::define(FacultyAtRiskAlerts::class);
        Feature::define(FacultyAssessments::class);
        Feature::define(FacultyInbox::class);
        Feature::define(FacultyOfficeHours::class);
        Feature::define(FacultyRequestsApprovals::class);
        Feature::define(FacultyInsights::class);
        Feature::define(FacultyGrades::class);
        Feature::define(FacultyAttendance::class);
        Feature::define(FacultyResources::class);
        Feature::define(FacultyForms::class);
        Feature::define(FacultyDeveloperMode::class);
        Feature::define(AdminDeveloperMode::class);
        Feature::define(StudentDashboard::class);
        Feature::define(StudentClasses::class);
        Feature::define(StudentTuition::class);
        Feature::define(StudentSchedule::class);
        Feature::define(StudentAnnouncements::class);
        Feature::define(StudentSettings::class);
        Feature::define(StudentInformationUpdates::class);
        Feature::define(StudentHelp::class);
        Feature::define(StudentGradesPreview::class);
        Feature::define(StudentAttendanceTracker::class);
        Feature::define(StudentDeveloperMode::class);
        Feature::define(StudentSignaturePad::class);
        Feature::define(StudentAvatarUpload::class);
        Feature::define(OnlineCollegeEnrollment::class);
        Feature::define(OnlineTesdaEnrollment::class);
    }

    /**
     * Dynamically sync the Filament Feature Showcase config with GitHub
     * releases (stable only) and the local version.json file.
     */
    private function syncFeatureShowcaseConfig(): void
    {
        try {
            $changelogService = app(ChangelogService::class);
            $versionService = app(VersionService::class);

            $versionData = $versionService->getVersionData();
            $showcaseChangelog = $changelogService->getShowcaseChangelog();
            $latestStable = $changelogService->getLatestStableVersion();

            // Prefer version.json version; fall back to latest GitHub stable release
            $currentVersion = ($versionData['version'] ?? null)
                ?? $latestStable
                ?? config('filament-feature-showcase.current');

            config([
                'filament-feature-showcase.current' => $currentVersion,
            ]);

            // Only override changelog if GitHub returned data; otherwise keep config fallback
            if ($showcaseChangelog !== []) {
                config([
                    'filament-feature-showcase.changelog' => $showcaseChangelog,
                ]);
            }
        } catch (Throwable $e) {
            // Silently keep static config as fallback if dynamic fetch fails
            Log::warning('Failed to sync feature showcase config dynamically', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Apply admin-configured error reporting settings over the env-based defaults.
     * Best-effort: skipped during console setup/migration when the DB is unavailable.
     */
    private function applySentrySettings(): void
    {
        try {
            app(\App\Services\ErrorReportingService::class)->applyToConfig();
        } catch (Throwable $e) {
            Log::debug('Skipping error reporting settings sync', ['error' => $e->getMessage()]);
        }
    }

    private function removeMissingViewFinderPaths(): void
    {
        $finder = $this->app['view']->getFinder();

        if (! $finder instanceof FileViewFinder) {
            return;
        }

        $finder->setPaths($this->existingDirectories($finder->getPaths()));

        foreach ($finder->getHints() as $namespace => $hints) {
            $finder->replaceNamespace($namespace, $this->existingDirectories($hints));
        }

        $finder->flush();
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function existingDirectories(array $paths): array
    {
        return array_values(array_filter(
            $paths,
            is_dir(...),
        ));
    }
}
