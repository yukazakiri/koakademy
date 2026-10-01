<?php

declare(strict_types=1);

use App\Models\GeneralSetting;
use App\Services\GeneralSettingsService;
use App\Services\SentrySettingsService;
use Illuminate\Support\Facades\File;

/**
 * An earlier revision of the telemetry change wrapped Sentry's exception
 * registration in `if (filled(config('sentry.dsn')))`. That guard is wrong:
 * AppServiceProvider pushes the admin panel's DSN into config on the app's
 * `booted` callback, which runs after bootstrap/app.php has been evaluated, so
 * the condition is always false at that point and error reporting is silently
 * disabled for every installation that configures Sentry through the UI.
 *
 * These tests pin the two halves of that reasoning.
 */
it('registers the Sentry exception integration without a DSN guard', function (): void {
    $bootstrap = File::get(base_path('bootstrap/app.php'));

    expect($bootstrap)->toContain('Integration::handles($exceptions)');

    // Guarding registration on a DSN read during bootstrap is the exact
    // regression being prevented: config is not yet panel-aware here.
    expect($bootstrap)->not->toContain('filled(config(\'sentry.dsn\'))');
    expect($bootstrap)->not->toContain('filled(config("sentry.dsn"))');
});

it('resolves a DSN configured through the admin panel when the environment is empty', function (): void {
    config(['sentry.dsn' => null]);

    expect(config('sentry.dsn'))->toBeNull();

    app(GeneralSettingsService::class)->replaceGlobalSettings(null);
    app(SentrySettingsService::class)->resetCache();
    GeneralSetting::factory()->create();

    // This write happens on the booted callback, strictly after
    // bootstrap/app.php is evaluated.
    app(SentrySettingsService::class)->save([
        'enabled' => true,
        'dsn' => 'https://publickey@o1.ingest.sentry.io/2',
        'environment' => 'production',
        'release' => '',
        'sample_rate' => 1.0,
        'traces_sample_rate' => 0.0,
        'profiles_sample_rate' => null,
        'send_default_pii' => false,
        'enable_logs' => false,
    ]);

    expect(config('sentry.dsn'))->toBe('https://publickey@o1.ingest.sentry.io/2');
});
