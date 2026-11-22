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

it('matches portal and admin routes on loopback and additional trusted hosts in single-domain mode', function (): void {
    config([
        'app.portal_host' => 'school.example',
        'app.portal_host_aliases' => [],
        'app.admin_host' => 'school.example',
    ]);

    require base_path('routes/web.php');

    $domainRequest = Illuminate\Http\Request::create('https://school.example/');
    $loopbackRequest = Illuminate\Http\Request::create('http://127.0.0.1:8000/');
    $trustedAliasRequest = Illuminate\Http\Request::create('http://192.168.1.50:8000/system-management/brand/appearance');

    expect(app('router')->getRoutes()->match($domainRequest)->uri())->toBe('/')
        ->and(app('router')->getRoutes()->match($loopbackRequest)->uri())->toBe('/')
        ->and(app('router')->getRoutes()->match($trustedAliasRequest)->getName())->toBe('admin.system-management.brand.appearance');
});
