<?php

declare(strict_types=1);

$providers = [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EnrollmentPolicyServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\PortalPanelProvider::class,
    App\Providers\NotificationChannelServiceProvider::class,
    App\Providers\PulseServiceProvider::class,
    App\Providers\SequenzyMailServiceProvider::class,
    App\Providers\TelescopeServiceProvider::class,
    EragLaravelPwa\EragLaravelPwaServiceProvider::class,
    SaaSykit\OpenGraphy\OpenGraphyServiceProvider::class,
];

// Horizon is no longer a project dependency; it is installed only in the
// production Docker images (see docker/Dockerfile / docker/Dockerfile.franken).
// Register its provider only when the package is actually present, otherwise
// booting the application would fatal with a "class not found" error.
if (class_exists(Laravel\Horizon\HorizonApplicationServiceProvider::class)) {
    $providers[] = App\Providers\HorizonServiceProvider::class;
}

return $providers;
