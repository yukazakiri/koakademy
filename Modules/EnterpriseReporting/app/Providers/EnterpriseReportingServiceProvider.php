<?php

declare(strict_types=1);

namespace Modules\EnterpriseReporting\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class EnterpriseReportingServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->routesAreCached()) {
            Route::middleware(['web', 'auth', 'administrators.only'])
                ->group(dirname(__DIR__, 2).'/routes/web.php');
        }
    }
}
