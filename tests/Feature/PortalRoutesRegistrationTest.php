<?php

declare(strict_types=1);

it('matches portal and admin routes on both localhost and 127.0.0.1 by default', function (): void {
    config([
        'app.portal_host' => 'localhost',
        'app.portal_host_aliases' => ['127.0.0.1'],
        'app.admin_host' => 'localhost',
    ]);

    require base_path('routes/web.php');

    $localhostRequest = Illuminate\Http\Request::create('http://localhost:8000/');
    $ipRequest = Illuminate\Http\Request::create('http://127.0.0.1:8000/');

    $localhostRoute = app('router')->getRoutes()->match($localhostRequest);
    $ipRoute = app('router')->getRoutes()->match($ipRequest);

    expect($localhostRoute->uri())->toBe('/')
        ->and($ipRoute->uri())->toBe('/');

    $service = app(App\Services\SettingsShareService::class);
    expect($service->isPortalDomain($localhostRequest))->toBeTrue()
        ->and($service->isPortalDomain($ipRequest))->toBeTrue();

    $localhostAdmin = Illuminate\Http\Request::create('http://localhost:8000/system-management/brand/appearance');
    $ipAdmin = Illuminate\Http\Request::create('http://127.0.0.1:8000/system-management/brand/appearance');

    expect(app('router')->getRoutes()->match($localhostAdmin)->getName())->toBe('admin.system-management.brand.appearance')
        ->and(app('router')->getRoutes()->match($ipAdmin)->getName())->toBe('admin.system-management.brand.appearance');
});
