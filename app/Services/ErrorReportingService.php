<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeneralSetting;
use Exception;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Multi-provider error reporting registry.
 *
 * Sentry is the only provider whose SDK ships with the application; Flare,
 * Bugsnag and Honeybadger store credentials and runtime config so they
 * activate as soon as their SDKs are installed.
 */
final class ErrorReportingService
{
    /** @var list<string> */
    public const array PROVIDER_KEYS = ['sentry', 'flare', 'bugsnag', 'honeybadger'];

    private const string CONFIG_KEY = 'error_reporting';

    public function __construct(
        private readonly SentrySettingsService $sentrySettings,
    ) {}

    /**
     * @return array<string, array{key: string, label: string, description: string, package: string, docs_url: string, install_command: string, installed: bool}>
     */
    public function meta(): array
    {
        return [
            'sentry' => [
                'key' => 'sentry',
                'label' => 'Sentry',
                'description' => 'Exception capture, performance tracing, and browser error tracking.',
                'package' => 'sentry/sentry-laravel',
                'docs_url' => 'https://docs.sentry.io/platforms/php/guides/laravel/',
                'install_command' => 'composer require sentry/sentry-laravel',
                'installed' => class_exists(\Sentry\SentrySdk::class),
            ],
            'flare' => [
                'key' => 'flare',
                'label' => 'Flare',
                'description' => 'Spatie error tracking with solutions and request context.',
                'package' => 'spatie/laravel-flare',
                'docs_url' => 'https://github.com/spatie/laravel-flare',
                'install_command' => 'composer require spatie/laravel-flare',
                'installed' => class_exists(\Spatie\FlareClient\Flare::class),
            ],
            'bugsnag' => [
                'key' => 'bugsnag',
                'label' => 'Bugsnag',
                'description' => 'Stability monitoring with release tracking and breadcrumbs.',
                'package' => 'bugsnag/bugsnag-laravel',
                'docs_url' => 'https://github.com/bugsnag/bugsnag-laravel',
                'install_command' => 'composer require bugsnag/bugsnag-laravel',
                'installed' => class_exists(\Bugsnag\Client::class),
            ],
            'honeybadger' => [
                'key' => 'honeybadger',
                'label' => 'Honeybadger',
                'description' => 'Exception monitoring with check-ins and status pages.',
                'package' => 'honeybadger-io/honeybadger-laravel',
                'docs_url' => 'https://github.com/honeybadger-io/honeybadger-laravel',
                'install_command' => 'composer require honeybadger-io/honeybadger-laravel',
                'installed' => class_exists(\Honeybadger\Honeybadger::class),
            ],
        ];
    }

    public function isInstalled(string $provider): bool
    {
        return (bool) ($this->meta()[$provider]['installed'] ?? false);
    }

    /**
     * @return array{providers: array<string, array<string, mixed>>, meta: array<string, array<string, mixed>>}
     */
    public function get(): array
    {
        return [
            'providers' => [
                'sentry' => $this->sentrySettings->get(),
                'flare' => $this->simpleProvider('flare'),
                'bugsnag' => $this->simpleProvider('bugsnag'),
                'honeybadger' => $this->simpleProvider('honeybadger'),
            ],
            'meta' => $this->meta(),
        ];
    }

    /** @return array{providers: array<string, array<string, mixed>>, meta: array<string, array<string, mixed>>} */
    public function forAdministration(): array
    {
        return $this->get();
    }

    /** @return list<string> */
    public function enabledProviders(): array
    {
        return array_values(array_filter(
            self::PROVIDER_KEYS,
            fn (string $key): bool => (bool) ($this->get()['providers'][$key]['enabled'] ?? false)
        ));
    }

    /**
     * @param  array<string, mixed>  $attributes  Validated request payload with a `providers` key.
     * @return array{providers: array<string, array<string, mixed>>, meta: array<string, array<string, mixed>>}
     */
    public function save(array $attributes): array
    {
        $providers = $attributes['providers'] ?? [];

        if (! is_array($providers)) {
            $providers = [];
        }

        $this->sentrySettings->save(is_array($providers['sentry'] ?? null) ? $providers['sentry'] : []);

        $settings = GeneralSetting::query()->first();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => (string) config('app.name', 'KoAkademy'),
            ]);
        }

        $moreConfigs = is_array($settings->more_configs) ? $settings->more_configs : [];
        $errorReporting = is_array($moreConfigs[self::CONFIG_KEY] ?? null) ? $moreConfigs[self::CONFIG_KEY] : [];
        $storedProviders = is_array($errorReporting['providers'] ?? null) ? $errorReporting['providers'] : [];

        foreach (['flare', 'bugsnag', 'honeybadger'] as $key) {
            $storedProviders[$key] = $this->sanitizeSimpleProvider($key, $providers[$key] ?? []);
        }

        // Sentry owns its own row (including legacy migration); keep the aggregate in sync.
        $storedProviders['sentry'] = $this->sentrySettings->sanitize($providers['sentry'] ?? []);
        $errorReporting['providers'] = $storedProviders;
        $moreConfigs[self::CONFIG_KEY] = $errorReporting;

        $settings->update(['more_configs' => $moreConfigs]);
        GeneralSetting::clearCache();

        $this->applyToConfig();

        return $this->get();
    }

    /**
     * Sanitize candidate provider rows without persisting (used by the probe endpoint).
     *
     * @return array<string, array<string, mixed>>
     */
    public function sanitizeProviders(mixed $providers): array
    {
        $providers = is_array($providers) ? $providers : [];

        return [
            'sentry' => $this->sentrySettings->sanitize($providers['sentry'] ?? []),
            'flare' => $this->sanitizeSimpleProvider('flare', $providers['flare'] ?? []),
            'bugsnag' => $this->sanitizeSimpleProvider('bugsnag', $providers['bugsnag'] ?? []),
            'honeybadger' => $this->sanitizeSimpleProvider('honeybadger', $providers['honeybadger'] ?? []),
        ];
    }

    /**
     * Push every provider's credentials into runtime config. Harmless when a
     * provider's SDK is not installed; the values activate on install.
     */
    public function applyToConfig(): void
    {
        $this->sentrySettings->applyToConfig();

        try {
            $config = $this->get()['providers'];

            if (($config['flare']['api_key'] ?? '') !== '') {
                config(['flare.key' => $config['flare']['api_key']]);
            }

            if (($config['bugsnag']['api_key'] ?? '') !== '') {
                config(['bugsnag.api_key' => $config['bugsnag']['api_key']]);
            }

            if (($config['honeybadger']['api_key'] ?? '') !== '') {
                config(['honeybadger.api_key' => $config['honeybadger']['api_key']]);
            }
        } catch (Throwable) {
            // Best-effort only; reporting must never break boot.
        }
    }

    /**
     * Send a probe event through one provider using the candidate (possibly
     * unsaved) configuration.
     *
     * @param  array<string, mixed>  $candidateProviders  Sanitized provider rows.
     *
     * @throws RuntimeException when the probe cannot be delivered.
     */
    public function testProvider(string $provider, array $candidateProviders): void
    {
        if (! in_array($provider, self::PROVIDER_KEYS, true)) {
            throw new RuntimeException("Unknown error reporting provider [{$provider}].");
        }

        if (! $this->isInstalled($provider)) {
            $package = $this->meta()[$provider]['package'];

            throw new RuntimeException("The {$package} SDK is not installed. Run `{$this->meta()[$provider]['install_command']}` first.");
        }

        $candidate = $candidateProviders[$provider] ?? [];

        if (! is_array($candidate) || mb_trim((string) ($candidate['dsn'] ?? $candidate['api_key'] ?? '')) === '') {
            throw new RuntimeException('Configure a credential for this provider before sending a test event.');
        }

        match ($provider) {
            'sentry' => $this->testSentry($candidateProviders),
            'flare' => $this->testFlare($candidate),
            'bugsnag' => $this->testBugsnag($candidate),
            'honeybadger' => $this->testHoneybadger($candidate),
        };
    }

    /** @param array<string, mixed> $candidateProviders */
    private function testSentry(array $candidateProviders): void
    {
        $sentry = $candidateProviders['sentry'] ?? [];

        if (! is_array($sentry)) {
            throw new RuntimeException('Missing Sentry configuration.');
        }

        // Force delivery for the probe regardless of the configured sample rate.
        $sentry['sample_rate'] = 1.0;
        $this->sentrySettings->applyToConfig($sentry);

        try {
            \Sentry\withScope(function (\Sentry\State\Scope $scope): void {
                $scope->setTag('sentry.test', 'admin-panel');
                \Sentry\captureMessage('Sentry test event from KoAkademy admin panel');
            });
            \Sentry\SentrySdk::flush(2000);
        } finally {
            $this->sentrySettings->applyToConfig();
        }
    }

    /** @param array<string, mixed> $candidate */
    private function testFlare(array $candidate): void
    {
        config(['flare.key' => $candidate['api_key'] ?? null]);

        $facade = \Spatie\LaravelFlare\Facades\Flare::class;

        if (! class_exists($facade) || ! method_exists($facade, 'report')) {
            throw new RuntimeException('The installed Flare SDK does not expose a report API this panel can call.');
        }

        $facade::report(new Exception('Flare test event from KoAkademy admin panel'));
    }

    /** @param array<string, mixed> $candidate */
    private function testBugsnag(array $candidate): void
    {
        config(['bugsnag.api_key' => $candidate['api_key'] ?? null]);

        $facade = \Bugsnag\BugsnagLaravel\Facades\Bugsnag::class;

        if (! class_exists($facade) || ! method_exists($facade, 'notifyException')) {
            throw new RuntimeException('The installed Bugsnag SDK does not expose a report API this panel can call.');
        }

        $facade::notifyException(new Exception('Bugsnag test event from KoAkademy admin panel'));
    }

    /** @param array<string, mixed> $candidate */
    private function testHoneybadger(array $candidate): void
    {
        config(['honeybadger.api_key' => $candidate['api_key'] ?? null]);

        $facade = \Honeybadger\HoneybadgerLaravel\Facades\Honeybadger::class;

        if (! class_exists($facade) || ! method_exists($facade, 'notify')) {
            throw new RuntimeException('The installed Honeybadger SDK does not expose a report API this panel can call.');
        }

        $facade::notify(new Exception('Honeybadger test event from KoAkademy admin panel'));
    }

    /** @return array{enabled: bool, api_key: string, environment: string, release: string} */
    private function simpleProvider(string $key): array
    {
        $saved = $this->storedProviders()[$key] ?? [];
        $saved = is_array($saved) ? $saved : [];

        return [
            'enabled' => $this->resolveBool($saved['enabled'] ?? null, false),
            'api_key' => $this->normalizeString($saved['api_key'] ?? $this->envApiKey($key)),
            'environment' => $this->normalizeString($saved['environment'] ?? null) ?: 'production',
            'release' => $this->normalizeString($saved['release'] ?? ''),
        ];
    }

    /**
     * @return array{enabled: bool, api_key: string, environment: string, release: string}
     */
    private function sanitizeSimpleProvider(string $key, mixed $attributes): array
    {
        $attributes = is_array($attributes) ? $attributes : [];

        $apiKey = $this->normalizeString($attributes['api_key'] ?? '');
        if ($apiKey === '') {
            $apiKey = $this->normalizeString($this->envApiKey($key));
        }

        return [
            'enabled' => (bool) ($attributes['enabled'] ?? false),
            'api_key' => mb_substr($apiKey, 0, 2048),
            'environment' => mb_substr($this->normalizeString($attributes['environment'] ?? 'production') ?: 'production', 0, 64),
            'release' => mb_substr($this->normalizeString($attributes['release'] ?? ''), 0, 255),
        ];
    }

    private function envApiKey(string $key): string
    {
        return match ($key) {
            'flare' => (string) env('FLARE_KEY', ''),
            'bugsnag' => (string) env('BUGSNAG_API_KEY', ''),
            'honeybadger' => (string) env('HONEYBADGER_API_KEY', ''),
            default => '',
        };
    }

    /** @return array<string, mixed> */
    private function storedProviders(): array
    {
        try {
            if (! Schema::hasTable('general_settings')) {
                return [];
            }

            $moreConfigs = GeneralSetting::query()->first()?->more_configs;

            if (! is_array($moreConfigs)) {
                return [];
            }

            $errorReporting = $moreConfigs[self::CONFIG_KEY] ?? [];

            if (! is_array($errorReporting)) {
                return [];
            }

            $providers = $errorReporting['providers'] ?? [];

            return is_array($providers) ? $providers : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveBool(mixed $value, bool $fallback): bool
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $fallback;
    }

    private function normalizeString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = mb_trim((string) $value);

        if ($string === '' || mb_check_encoding($string, 'UTF-8')) {
            return $string;
        }

        return (string) mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
}
