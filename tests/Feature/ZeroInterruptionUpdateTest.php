<?php

declare(strict_types=1);

use App\Services\AppBuildVersionService;

it('computes opaque inertia versions and honors force-reload epochs', function (): void {
    $service = app(AppBuildVersionService::class);
    $baseAssetVersion = $service->resolveAssetVersion();

    config([
        'app.updates.soft_reload' => true,
        'app.updates.force_reload_version' => '',
    ]);

    expect($service->inertiaVersion())->toBe($baseAssetVersion)
        ->and($service->requiresHardReload('stale-client-version', $service->inertiaVersion()))->toBeFalse()
        ->and($service->requiresHardReload('', $service->inertiaVersion()))->toBeTrue()
        ->and($service->requiresHardReload(null, $service->inertiaVersion()))->toBeTrue();

    config(['app.updates.soft_reload' => false]);
    expect($service->requiresHardReload('stale-client-version', $service->inertiaVersion()))->toBeTrue();

    config([
        'app.updates.soft_reload' => true,
        'app.updates.force_reload_version' => 'v2',
    ]);

    $epochVersion = $service->inertiaVersion();
    expect($epochVersion)->toStartWith('v2:')
        ->and($service->requiresHardReload('stale-client-version', $epochVersion))->toBeTrue()
        ->and($service->requiresHardReload('v1:old-hash', $epochVersion))->toBeTrue()
        ->and($service->requiresHardReload('v2:old-hash', $epochVersion))->toBeFalse();
});

it('serves a non-interruptive 200 Inertia response on version mismatch when soft reload is enabled', function (): void {
    config([
        'app.updates.soft_reload' => true,
        'app.updates.force_reload_version' => '',
    ]);

    $service = app(AppBuildVersionService::class);
    $serverVersion = $service->inertiaVersion();

    expect($serverVersion)->not->toBeNull();

    $clientVersion = 'previous-release-asset-hash';

    $response = $this->get(route('login'), [
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $clientVersion,
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response
        ->assertSuccessful()
        ->assertHeader('X-Inertia', 'true')
        ->assertHeader('X-App-Update-Available', 'true')
        ->assertHeader('X-App-Build-Id', (string) $serverVersion)
        ->assertJsonPath('version', $clientVersion)
        ->assertJsonPath('props.appBuild.buildId', $serverVersion)
        ->assertJsonPath('props.appBuild.softReload', true);
});

it('does not flag an update when the client version matches the server version', function (): void {
    config([
        'app.updates.soft_reload' => true,
        'app.updates.force_reload_version' => '',
    ]);

    $serverVersion = (string) app(AppBuildVersionService::class)->inertiaVersion();

    $response = $this->get(route('login'), [
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $serverVersion,
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response
        ->assertSuccessful()
        ->assertHeader('X-Inertia', 'true')
        ->assertHeaderMissing('X-App-Update-Available')
        ->assertJsonPath('version', $serverVersion)
        ->assertJsonPath('props.appBuild.buildId', $serverVersion);
});

it('injects appBuild metadata on version-mismatched partial Inertia reloads without forcing 409', function (): void {
    config([
        'app.updates.soft_reload' => true,
        'app.updates.force_reload_version' => '',
    ]);

    $serverVersion = (string) app(AppBuildVersionService::class)->inertiaVersion();
    $clientVersion = 'previous-release-asset-hash';

    $response = $this->get(route('login'), [
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $clientVersion,
        'X-Inertia-Partial-Component' => 'login',
        'X-Inertia-Partial-Data' => 'status',
        'X-Requested-With' => 'XMLHttpRequest',
    ]);

    $response
        ->assertSuccessful()
        ->assertHeader('X-App-Update-Available', 'true')
        ->assertHeader('X-App-Build-Id', $serverVersion)
        ->assertJsonPath('version', $clientVersion)
        ->assertJsonPath('props.appBuild.buildId', $serverVersion);
});

it('returns 409 conflict when soft reload is disabled', function (): void {
    config([
        'app.updates.soft_reload' => false,
        'app.updates.force_reload_version' => '',
    ]);

    $this->get(route('login'), [
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'previous-release-asset-hash',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');
});

it('returns 409 conflict when the force-reload epoch changes', function (): void {
    config([
        'app.updates.soft_reload' => true,
        'app.updates.force_reload_version' => 'breaking-2',
    ]);

    $this->get(route('login'), [
        'Accept' => 'text/html, application/xhtml+xml',
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'breaking-1:previous-release-asset-hash',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location');
});

it('exposes opaque build metadata on /build-version without leaking app semver', function (): void {
    config([
        'app.version' => '9.9.9-secret',
        'app.updates.soft_reload' => true,
        'app.updates.force_reload_version' => '',
        'app.updates.poll_interval_seconds' => 180,
    ]);

    $serverVersion = app(AppBuildVersionService::class)->inertiaVersion();

    $response = $this->getJson(route('app.build-version'));

    $response
        ->assertSuccessful()
        ->assertHeader('X-App-Build-Id', (string) $serverVersion)
        ->assertJson([
            'buildId' => $serverVersion,
            'softReload' => true,
            'pollIntervalSeconds' => 180,
        ]);

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->getContent())->not->toContain('9.9.9-secret');
});

it('runs migrations behind a blocking lock via app:migrate-locked', function (): void {
    $this->artisan('app:migrate-locked', [
        '--lock-timeout' => 5,
    ])->assertExitCode(0);
});

it('fails cleanly when the migration lock is held past lock-timeout', function (): void {
    config(['cache.default' => 'array']);

    $connectionName = (string) config('database.default');
    $lock = Illuminate\Support\Facades\Cache::store('array')->lock("koakademy:migrations:{$connectionName}", 60);

    expect($lock->get())->toBeTrue();

    try {
        $this->artisan('app:migrate-locked', [
            '--lock-timeout' => 1,
        ])
            ->expectsOutputToContain('Timed out after 1s waiting for the cache migration lock')
            ->assertExitCode(1);
    } finally {
        $lock->release();
    }

    $this->artisan('app:migrate-locked', [
        '--lock-timeout' => 5,
    ])->assertExitCode(0);
});
