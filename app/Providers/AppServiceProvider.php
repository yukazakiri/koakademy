<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AssessmentFormPdfRenderer;
use App\Enums\UserRole;
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
use App\Scout\TermDatabaseEngine;
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
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\FileViewFinder;
use Laravel\Passkeys\Passkeys;
use Laravel\Pennant\Feature;
use Laravel\Scout\EngineManager;
use Livewire\Livewire;
use Nwidart\Modules\Contracts\RepositoryInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Throwable;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(GeneralSettingsService::class);
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
        $this->app->scoped(\App\Services\Ai\AiSettingsService::class);
        $this->app->scoped(\App\Services\Ai\AiModelFetchService::class);
        $this->app->scoped(\App\Services\SentrySettingsService::class);
        $this->app->scoped(\App\Services\ErrorReportingService::class);
        $this->app->scoped(\App\Services\TenantContext::class);
        $this->app->scoped(\App\Services\FeatureAccessService::class);
        $this->app->bind(AssessmentFormPdfRenderer::class, LaravelAssessmentFormPdfRenderer::class);

        $this->app->singleton(\Tighten\Ziggy\BladeRouteGenerator::class, function (): \Tighten\Ziggy\BladeRouteGenerator {
            return new class extends \Tighten\Ziggy\BladeRouteGenerator
            {
                public function generate(array|string|null $group = null, ?string $nonce = null, ?bool $json = false): string
                {
                    $ziggyManifestHash = is_file(config_path('ziggy.php')) ? (string) filemtime(config_path('ziggy.php')) : '';
                    $cacheKey = 'ziggy_blade_script_'.md5(serialize([$group, $nonce, $json, config('app.url'), config('app.version'), $ziggyManifestHash]));

                    return \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($group, $nonce, $json): string {
                        return parent::generate($group, $nonce, $json);
                    });
                }
            };
        });
    }

    public function boot(): void
    {
        $this->resetPhpSpreadsheetValueBinderBetweenUnitsOfWork();

        // Global (command palette) search: every term of a multi-word query must
        // match, instead of the whole query being treated as one literal phrase.
        resolve(EngineManager::class)->extend('database', static fn (): TermDatabaseEngine => new TermDatabaseEngine);

        RateLimiter::for('global-search', function (Request $request): Limit {
            return Limit::perMinute(90)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

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

        RateLimiter::for('ai-chat', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.ai_rate_limit', 30))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('mcp', function (Request $request): Limit {
            return Limit::perMinute((int) config('api.mcp.rate_limit', 30))
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        Model::unguard();
        StudentTransaction::observe(StudentTransactionObserver::class);

        Passkeys::useUserModel(User::class);
        Passkeys::usePasskeyModel(Passkey::class);

        if (app()->environment('production') && HostingSecurity::usesHttps((string) config('app.url'))) {
            URL::forceScheme('https');
        }

        Gate::define('viewApiDocs', fn (User $user): bool => $user->hasRole('super_admin'));
        Gate::define('viewLaralyze', function (?User $user): bool {
            if (! $user instanceof User) {
                return false;
            }

            if (in_array($user->role, [UserRole::SuperAdmin, UserRole::Developer], true)) {
                return true;
            }

            return $user->hasAnyRole([UserRole::Developer->value, UserRole::SuperAdmin->value]);
        });

        Livewire::component(
            'daacreators.pennant-manager.widgets.feature-adoption-widget',
            PennantFeatureAdoptionWidget::class,
        );

        if ($this->app->runningInConsole()) {
            Event::listen(\Illuminate\Console\Events\CommandStarting::class, function (\Illuminate\Console\Events\CommandStarting $event): void {
                if ($event->command === 'wayfinder:generate') {
                    $current = ini_get('memory_limit');
                    if ($current !== '-1') {
                        ini_set('memory_limit', '512M');
                    }
                }
            });
        }

        $this->definePennantFeatures();

        // Dynamically populate the Filament Feature Showcase config from
        // GitHub releases (stable only) and version.json so the changelog
        // stays in sync without manual config edits.
        $this->syncFeatureShowcaseConfig();

        $this->configureDevCommands();

        $this->app->booted(function (): void {
            $this->removeMissingViewFinderPaths();
            $this->applySentrySettings();
            $this->applyAiSettings();
        });
    }

    /**
     * Keep Horizon out of the `php artisan dev` stack unless it is asked for.
     *
     * Horizon registers itself during its service provider's register() phase
     * and calls DevCommands::except('queue') at that point, which removes the
     * default queue listener so only Horizon drains the queues. With the
     * database queue driver this app ships with, Horizon has no Redis to
     * supervise, so it is dead weight in local development.
     *
     * DevCommands::except() replaces the excluded list wholesale rather than
     * appending to it, and every provider's register() has already run by the
     * time boot() executes, so overriding it here is safe: listing only
     * "horizon" drops Horizon and lets the default `queue:listen` worker come
     * back, giving the same process set as the composer "dev" script.
     */
    private function configureDevCommands(): void
    {
        if (! config('horizon.dev_command')) {
            DevCommands::except('horizon');
        }
    }

    /**
     * Bootstrap any application services.
     */
    /**
     * Keep PhpSpreadsheet's global value binder from leaking between units of work.
     *
     * Maatwebsite installs the sheet or import currently being written as
     * PhpSpreadsheet's process-wide static value binder and never restores the
     * default. Exports in this application bind every value as text so that
     * identifiers keep their leading zeros, so a registrar export leaves a
     * force-to-text binder installed for the rest of the process. Every
     * spreadsheet built afterwards then has `setCellValue()` coerced to a
     * string, which silently turns formulas into literal text: a CHED Form B/C
     * report generated afterwards stored `=B25+C25` as that literal string, so
     * `getCalculatedValue()` returned the formula text and every total read
     * zero. In a queue worker that runs a registrar export and then a
     * regulatory report, that is a report filed with wrong numbers rather than
     * an error.
     *
     * Resetting at the request and job boundaries contains the leak without
     * having to trust Maatwebsite to restore it, and works on the write paths
     * that dispatch no events.
     */
    private function resetPhpSpreadsheetValueBinderBetweenUnitsOfWork(): void
    {
        $reset = static function (): void {
            Cell::setValueBinder(new DefaultValueBinder);
        };

        $this->app->terminating($reset);

        Event::listen([
            JobProcessing::class,
            JobProcessed::class,
            JobFailed::class,
            JobExceptionOccurred::class,
        ], $reset);

        Event::listen(JobProcessing::class, static function (): void {
            Feature::flushCache();
            app(\App\Services\FeatureAccessService::class)->reset();
        });
    }

    private function definePennantFeatures(): void
    {
        foreach (\App\Services\FeatureToggleRegistry::allClasses() as $class) {
            Feature::define($class);
        }
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

    /**
     * Apply admin-configured AI provider settings over the env-based defaults.
     */
    private function applyAiSettings(): void
    {
        try {
            app(\App\Services\Ai\AiSettingsService::class)->applyRuntimeConfig();
        } catch (Throwable $e) {
            Log::debug('Skipping AI settings sync', ['error' => $e->getMessage()]);
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
