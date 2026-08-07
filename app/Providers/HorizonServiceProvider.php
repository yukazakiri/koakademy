<?php

declare(strict_types=1);

namespace App\Providers;

use Laravel\Horizon\HorizonApplicationServiceProvider;

final class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * The "viewHorizon" gate that guards the dashboard in non-local
     * environments is defined in AuthServiceProvider.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Intentionally left empty: the "viewHorizon" gate is defined in
     * AuthServiceProvider. The parent implementation would define an
     * empty e-mail allow-list and override it, since this provider
     * boots after AuthServiceProvider.
     */
    protected function gate(): void
    {
        //
    }
}
