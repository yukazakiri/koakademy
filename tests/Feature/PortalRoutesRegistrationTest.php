<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

it('registers administrator and portal routes when portal host is empty', function (): void {
    config([
        'app.portal_host' => '',
        'app.portal_host_aliases' => [],
        'app.admin_host' => '',
    ]);

    // Re-evaluate routes/web.php with empty hosts
    require base_path('routes/web.php');

    expect(Route::has('administrators.registrar.analytics.index'))->toBeTrue()
        ->and(Route::has('administrators.enrollments.index'))->toBeTrue()
        ->and(Route::has('admin.system-management.brand.appearance'))->toBeTrue()
        ->and(Route::has('finance-documents.verify'))->toBeTrue();
});

it('registers portal routes for each configured alias', function (): void {
    config([
        'app.portal_host' => 'school.example',
        'app.portal_host_aliases' => ['portal.school.example'],
    ]);

    require base_path('routes/web.php');

    expect(Route::has('administrators.registrar.analytics.index'))->toBeTrue();
});

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
