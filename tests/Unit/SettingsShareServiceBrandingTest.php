<?php

declare(strict_types=1);

use App\Services\SettingsShareService;
use App\Settings\SiteSettings;
use Illuminate\Http\Request;

it('uses koakademy defaults when site branding settings are empty', function (): void {
    $settings = app(SiteSettings::class);
    $settings->name = null;
    $settings->app_name = null;
    $settings->app_short_name = null;
    $settings->organization_name = null;
    $settings->organization_short_name = null;

    expect($settings->getAppName())->toBe('KoAkademy')
        ->and($settings->getAppShortName())->toBe('KOAK')
        ->and($settings->getOrganizationName())->toBe('KoAkademy')
        ->and($settings->getOrganizationShortName())->toBe('KOAK');
});

it('shares normalized branding values for frontend consumers', function (): void {
    $settings = app(SiteSettings::class);
    $settings->app_name = 'Campus Suite';
    $settings->app_short_name = 'CS';
    $settings->organization_name = 'Campus Suite University';
    $settings->organization_short_name = 'CSU';
    $settings->tagline = 'Built for modern campuses';
    $settings->theme_color = '#225588';
    $settings->currency = 'USD';
    $settings->logo = '/storage/branding/logo.png';
    $settings->favicon = '/storage/branding/favicon.png';

    $branding = app(SettingsShareService::class)->getBranding();

    expect($branding)
        ->toMatchArray([
            'appName' => 'Campus Suite',
            'appShortName' => 'CS',
            'organizationName' => 'Campus Suite University',
            'organizationShortName' => 'CSU',
            'tagline' => 'Built for modern campuses',
            'themeColor' => '#225588',
            'currency' => 'USD',
            'logo' => '/storage/branding/logo.png',
            'favicon' => '/storage/branding/favicon.png',
        ]);
});

it('detects the configured portal host and ignores legacy portal names on that domain', function (): void {
    config(['app.portal_host' => 'localhost']);

    $settings = app(SiteSettings::class);
    $settings->app_name = 'KoAkademy';
    $settings->portal_name = 'KoAkademy';

    $service = app(SettingsShareService::class);
    $portalRequest = Request::create('https://localhost/login');
    $adminRequest = Request::create('https://127.0.0.1/login');

    expect($service->isPortalDomain($portalRequest))->toBeTrue()
        ->and($service->getAppName($portalRequest))->toBe('KoAkademy')
        ->and($service->isPortalDomain($adminRequest))->toBeFalse()
        ->and($service->getAppName($adminRequest))->toBe('KoAkademy');
});

it('keeps a custom portal name on the portal domain', function (): void {
    config(['app.portal_host' => 'localhost']);

    $settings = app(SiteSettings::class);
    $settings->app_name = 'KoAkademy';
    $settings->portal_name = 'KoAkademy Faculty Portal';

    $service = app(SettingsShareService::class);
    $portalRequest = Request::create('https://localhost/login');

    expect($service->getAppName($portalRequest))->toBe('KoAkademy Faculty Portal');
});

it('resolves relative branding asset paths to storage URLs and preserves absolute URLs', function (): void {
    $defaultDisk = config('filesystems.default');
    config(["filesystems.disks.{$defaultDisk}.url" => 'https://storage.koakademy.edu.ph']);

    $settings = app(SiteSettings::class);
    $settings->logo = 'branding/logo.png';
    $settings->favicon = 'https://cdn.example.com/favicon.png';

    expect($settings->getLogo())->toBe('https://storage.koakademy.edu.ph/branding/logo.png')
        ->and($settings->getFavicon())->toBe('https://cdn.example.com/favicon.png');
});
