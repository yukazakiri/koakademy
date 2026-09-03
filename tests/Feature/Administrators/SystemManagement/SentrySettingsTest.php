<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\ErrorReportingService;
use App\Services\SentrySettingsService;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutMiddleware;

/** @return array<string, mixed> */
function validSentryProviderRow(array $overrides = []): array
{
    return array_merge([
        'enabled' => true,
        'dsn' => 'https://publickey@o123.ingest.sentry.io/456',
        'environment' => 'production',
        'release' => 'koakademy@1.0.0-test',
        'sample_rate' => 1.0,
        'traces_sample_rate' => 0.5,
        'profiles_sample_rate' => null,
        'send_default_pii' => false,
        'enable_logs' => false,
        'frontend_enabled' => true,
        'frontend_dsn' => '',
        'frontend_script' => '',
        'frontend_traces_sample_rate' => 0.1,
        'frontend_replays_session_sample_rate' => 0.0,
        'frontend_replays_on_error_sample_rate' => 1.0,
    ], $overrides);
}

/** @return array<string, mixed> */
function validSimpleProviderRow(array $overrides = []): array
{
    return array_merge([
        'enabled' => false,
        'api_key' => '',
        'environment' => 'production',
        'release' => '',
    ], $overrides);
}

/** @return array<string, mixed> */
function validErrorReportingPayload(array $overrides = []): array
{
    $payload = [
        'providers' => [
            'sentry' => validSentryProviderRow(),
            'flare' => validSimpleProviderRow(),
            'bugsnag' => validSimpleProviderRow(),
            'honeybadger' => validSimpleProviderRow(),
        ],
    ];

    return array_replace_recursive($payload, $overrides);
}

it('resolves Sentry settings as disabled when no DSN is configured', function (): void {
    GeneralSetting::factory()->create();

    $config = app(SentrySettingsService::class)->get();

    expect($config['enabled'])->toBeFalse()
        ->and($config['dsn'])->toBe('')
        ->and($config['environment'])->not->toBe('')
        ->and($config['traces_sample_rate'])->toBe(0.2);
});

it('persists Sentry settings and applies them to the runtime config', function (): void {
    GeneralSetting::factory()->create();

    $service = app(SentrySettingsService::class);
    $service->save(validSentryProviderRow());

    expect(config('sentry.dsn'))->toBe('https://publickey@o123.ingest.sentry.io/456')
        ->and(config('sentry.environment'))->toBe('production')
        ->and(config('sentry.traces_sample_rate'))->toBe(0.5);

    $service->save(validSentryProviderRow(['enabled' => false, 'dsn' => '']));

    expect(config('sentry.dsn'))->toBeNull();
});

it('exposes every provider with install metadata', function (): void {
    GeneralSetting::factory()->create();

    $config = app(ErrorReportingService::class)->get();

    expect(array_keys($config['providers']))->toBe(['sentry', 'flare', 'bugsnag', 'honeybadger'])
        ->and($config['meta']['sentry']['package'])->toBe('sentry/sentry-laravel')
        ->and($config['meta']['flare']['installed'])->toBeFalse()
        ->and($config['meta']['bugsnag']['installed'])->toBeFalse()
        ->and($config['meta']['honeybadger']['installed'])->toBeFalse()
        ->and($config['providers']['flare']['enabled'])->toBeFalse();
});

it('saves several providers in one request', function (): void {
    $settings = GeneralSetting::factory()->create();

    app(ErrorReportingService::class)->save(validErrorReportingPayload([
        'providers' => [
            'flare' => ['enabled' => true, 'api_key' => 'flare-test-key'],
            'bugsnag' => ['enabled' => true, 'api_key' => 'bugsnag-test-key', 'release' => 'koakademy@1.0.0-test'],
        ],
    ]));

    $settings->refresh();
    $providers = $settings->more_configs['error_reporting']['providers'] ?? [];

    expect($providers['sentry']['dsn'])->toBe('https://publickey@o123.ingest.sentry.io/456')
        ->and($providers['flare'])->toMatchArray(['enabled' => true, 'api_key' => 'flare-test-key'])
        ->and($providers['bugsnag'])->toMatchArray(['enabled' => true, 'api_key' => 'bugsnag-test-key', 'release' => 'koakademy@1.0.0-test'])
        ->and($providers['honeybadger']['enabled'])->toBeFalse()
        ->and(config('flare.key'))->toBe('flare-test-key')
        ->and(config('bugsnag.api_key'))->toBe('bugsnag-test-key');
});

it('migrates the legacy single-provider Sentry row', function (): void {
    $settings = GeneralSetting::factory()->create([
        'more_configs' => [
            'sentry' => validSentryProviderRow(['dsn' => 'https://legacy@o1.ingest.sentry.io/1']),
        ],
    ]);

    $config = app(SentrySettingsService::class)->get();

    expect($config['dsn'])->toBe('https://legacy@o1.ingest.sentry.io/1');

    app(ErrorReportingService::class)->save(validErrorReportingPayload());

    $settings->refresh();

    expect($settings->more_configs['error_reporting']['providers']['sentry']['dsn'])
        ->toBe('https://publickey@o123.ingest.sentry.io/456')
        ->and($settings->more_configs['sentry'] ?? null)->toBeNull();
});

it('renders the observability page with the error reporting payload', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['observability']);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/system-management/observability'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/system-management/observability', false)
            ->has('sentry')
            ->has('error_reporting')
            ->has('error_reporting.providers')
            ->has('error_reporting.meta')
            ->where('sentry.enabled', false));
});

it('updates error reporting settings from the observability form', function (): void {
    $settings = GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['observability']);
    withoutMiddleware();

    actingAs($user)
        ->put(
            portalUrlForAdministrators('/administrators/system-management/observability'),
            validErrorReportingPayload([
                'providers' => ['flare' => ['enabled' => true, 'api_key' => 'flare-test-key']],
            ])
        )
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings->refresh();
    $providers = $settings->more_configs['error_reporting']['providers'] ?? [];

    expect($providers['sentry']['dsn'])->toBe('https://publickey@o123.ingest.sentry.io/456')
        ->and($providers['sentry']['traces_sample_rate'])->toBe(0.5)
        ->and($providers['flare'])->toMatchArray(['enabled' => true, 'api_key' => 'flare-test-key'])
        ->and(config('sentry.dsn'))->toBe('https://publickey@o123.ingest.sentry.io/456');
});

it('rejects enabling Sentry without a DSN', function (): void {
    GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['observability']);
    withoutMiddleware();

    actingAs($user)
        ->put(
            portalUrlForAdministrators('/administrators/system-management/observability'),
            validErrorReportingPayload(['providers' => ['sentry' => ['dsn' => '']]])
        )
        ->assertSessionHasErrors('providers.sentry.dsn');
});

it('rejects enabling a provider without an API key', function (): void {
    GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['observability']);
    withoutMiddleware();

    actingAs($user)
        ->put(
            portalUrlForAdministrators('/administrators/system-management/observability'),
            validErrorReportingPayload(['providers' => ['bugsnag' => ['enabled' => true, 'api_key' => '']]])
        )
        ->assertSessionHasErrors('providers.bugsnag.api_key');
});

it('forbids error reporting updates without the update permission', function (): void {
    GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, []);
    withoutMiddleware();

    actingAs($user)
        ->put(portalUrlForAdministrators('/administrators/system-management/observability'), validErrorReportingPayload())
        ->assertForbidden();
});

it('refuses probe events when the provider SDK is not installed', function (): void {
    GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['observability']);
    withoutMiddleware();

    actingAs($user)
        ->postJson(
            portalUrlForAdministrators('/administrators/system-management/observability/test'),
            array_merge(validErrorReportingPayload(), ['provider' => 'flare'])
        )
        ->assertStatus(422)
        ->assertJsonPath('message', 'The spatie/laravel-flare SDK is not installed. Run `composer require spatie/laravel-flare` first.');
});

it('refuses probe events without a credential', function (): void {
    GeneralSetting::factory()->create();
    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    grantSystemManagementPermissions($user, ['observability']);
    withoutMiddleware();

    actingAs($user)
        ->postJson(
            portalUrlForAdministrators('/administrators/system-management/observability/test'),
            array_merge(
                validErrorReportingPayload(['providers' => ['sentry' => ['enabled' => false, 'dsn' => '']]]),
                ['provider' => 'sentry']
            )
        )
        ->assertStatus(422);
});
