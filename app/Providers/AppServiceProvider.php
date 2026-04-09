<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Handlers\ExportFailureHandler;
use App\Models\User;
use App\Services\GeneralSettingsService;
use App\Services\OnboardingShareService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ExportFailureHandler::class);
        // scoped = one instance per HTTP request, so Auth is always available when first used
        $this->app->scoped(GeneralSettingsService::class);
        $this->app->scoped(\App\Services\TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('viewApiDocs', fn (User $user): bool => $user->hasRole('super_admin'));

        \Laravel\Pennant\Feature::define('default-onboarding', fn (): bool => true);
        \Laravel\Pennant\Feature::define('onboarding-faculty', fn (): bool => true);
        \Laravel\Pennant\Feature::define('onboarding-student', fn (): bool => true);

        // Define features based on OnboardingFeature model to ensure sync
        foreach (array_keys(OnboardingShareService::FEATURE_TO_ROUTES) as $featureKey) {
            \Laravel\Pennant\Feature::define($featureKey, function ($user) use ($featureKey): bool {
                // If we have a user specific feature value in the DB (via Pennant), use it
                // Note: Pennant automatically checks its storage before calling this closure if configured to do so?
                // Actually, the closure is the "default" value resolution.

                // We check the OnboardingFeature table for the "global" switch
                try {
                    $feature = \App\Models\OnboardingFeature::where('feature_key', $featureKey)->first();

                    if (! $feature || ! $feature->is_active) {
                        return false;
                    }

                    // If active, check audience
                    if ($feature->audience === 'all') {
                        return true;
                    }

                    if ($user instanceof User) {
                        if ($feature->audience === 'student' && $user->isStudentRole()) {
                            return true;
                        }
                        if ($feature->audience === 'faculty' && $user->isFaculty()) {
                            return true;
                        }
                    }

                    return false;
                } catch (Throwable) {
                    return false;
                }
            });
        }
    }
}
