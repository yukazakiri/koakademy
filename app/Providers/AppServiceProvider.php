<?php

declare(strict_types=1);

namespace App\Providers;

use App\Features\Onboarding\FeatureClassRegistry;
use App\Filament\Handlers\ExportFailureHandler;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

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

        // Register all class-based Pennant features from the registry.
        // Pennant's discover() only looks at app/Features depth 0, so nested
        // namespaces (App\Features\Onboarding\*) must be registered explicitly.
        foreach (FeatureClassRegistry::allClasses() as $featureClass) {
            Feature::define($featureClass);
        }
    }
}
