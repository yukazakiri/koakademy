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
        ->and($settings->getAppShortName())->toBe('KOA')
        ->and($settings->getOrganizationName())->toBe('KoAkademy')
        ->and($settings->getOrganizationShortName())->toBe('KOA');
});

it('detects the configured portal host and prefers the portal name on that domain', function (): void {
    config(['app.portal_host' => 'portal.koakademy.test']);

    $settings = app(SiteSettings::class);
    $settings->app_name = 'KoAkademy';
    $settings->portal_name = 'KoAkademy Portal';

    $service = app(SettingsShareService::class);
    $portalRequest = Request::create('https://portal.koakademy.test/login');
    $adminRequest = Request::create('https://admin.koakademy.test/login');

    expect($service->isPortalDomain($portalRequest))->toBeTrue()
        ->and($service->getAppName($portalRequest))->toBe('KoAkademy Portal')
        ->and($service->isPortalDomain($adminRequest))->toBeFalse()
        ->and($service->getAppName($adminRequest))->toBe('KoAkademy');
});
