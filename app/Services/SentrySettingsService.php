<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeneralSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class SentrySettingsService
{
    private const string CONFIG_KEY = 'sentry';

    /**
     * @return array{
     *     enabled: bool,
     *     dsn: string,
     *     environment: string,
     *     release: string,
     *     sample_rate: float,
     *     traces_sample_rate: float,
     *     profiles_sample_rate: float|null,
     *     send_default_pii: bool,
     *     enable_logs: bool,
     *     frontend_enabled: bool,
     *     frontend_dsn: string,
     *     frontend_script: string,
     *     frontend_traces_sample_rate: float,
     *     frontend_replays_session_sample_rate: float,
     *     frontend_replays_on_error_sample_rate: float
     * }
     */
    public function get(): array
    {
        $saved = $this->savedConfig();

        $dsn = $this->normalizeString($saved['dsn'] ?? null);
        if ($dsn === '') {
            $dsn = $this->normalizeString(env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN', '')));
        }

        $environment = $this->normalizeString($saved['environment'] ?? null);
        if ($environment === '') {
            $environment = $this->normalizeString(env('SENTRY_ENVIRONMENT', (string) config('app.env', 'production')));
        }

        $release = $this->normalizeString($saved['release'] ?? null);
        if ($release === '') {
            $release = $this->normalizeString(env('SENTRY_RELEASE', ''));
        }

        $frontendDsn = $this->normalizeString($saved['frontend_dsn'] ?? null);
        if ($frontendDsn === '') {
            $frontendDsn = $this->normalizeString(env('SENTRY_FRONTEND_DSN', $dsn));
        }

        return [
            'enabled' => $this->resolveBool($saved['enabled'] ?? null, filled($dsn) && (bool) env('SENTRY_ENABLED', filled($dsn))),
            'dsn' => $dsn,
            'environment' => $environment !== '' ? $environment : 'production',
            'release' => $release,
            'sample_rate' => $this->resolveFloat($saved['sample_rate'] ?? null, $this->envFloat('SENTRY_SAMPLE_RATE', 1.0), 0.0, 1.0),
            'traces_sample_rate' => $this->resolveFloat($saved['traces_sample_rate'] ?? null, $this->envFloat('SENTRY_TRACES_SAMPLE_RATE', 0.2), 0.0, 1.0),
            'profiles_sample_rate' => $this->resolveNullableFloat($saved['profiles_sample_rate'] ?? null, $this->envNullableFloat('SENTRY_PROFILES_SAMPLE_RATE'), 0.0, 1.0),
            'send_default_pii' => $this->resolveBool($saved['send_default_pii'] ?? null, (bool) env('SENTRY_SEND_DEFAULT_PII', false)),
            'enable_logs' => $this->resolveBool($saved['enable_logs'] ?? null, (bool) env('SENTRY_ENABLE_LOGS', false)),
            'frontend_enabled' => $this->resolveBool($saved['frontend_enabled'] ?? null, (bool) env('SENTRY_FRONTEND_ENABLED', false)),
            'frontend_dsn' => $frontendDsn,
            'frontend_script' => $this->normalizeString($saved['frontend_script'] ?? env('SENTRY_FRONTEND_SCRIPT', '')),
            'frontend_traces_sample_rate' => $this->resolveFloat($saved['frontend_traces_sample_rate'] ?? null, $this->envFloat('SENTRY_FRONTEND_TRACES_SAMPLE_RATE', 0.1), 0.0, 1.0),
            'frontend_replays_session_sample_rate' => $this->resolveFloat($saved['frontend_replays_session_sample_rate'] ?? null, $this->envFloat('SENTRY_FRONTEND_REPLAYS_SESSION_SAMPLE_RATE', 0.0), 0.0, 1.0),
            'frontend_replays_on_error_sample_rate' => $this->resolveFloat($saved['frontend_replays_on_error_sample_rate'] ?? null, $this->envFloat('SENTRY_FRONTEND_REPLAYS_ON_ERROR_SAMPLE_RATE', 1.0), 0.0, 1.0),
        ];
    }

    /**
     * Admin-facing payload. DSN is intentionally returned in full so admins
     * can verify and rotate it; it is a public ingest endpoint, not a secret.
     *
     * @return array<string, mixed>
     */
    public function forAdministration(): array
    {
        return $this->get();
    }

    /**
     * Public-safe subset shared with the frontend for the JS loader snippet.
     *
     * @return array{enabled: bool, dsn: string, environment: string, release: string, script: string, traces_sample_rate: float, replays_session_sample_rate: float, replays_on_error_sample_rate: float}
     */
    public function frontendConfig(): array
    {
        $config = $this->get();

        $frontendEnabled = $config['frontend_enabled'] && $config['enabled'] && ($config['frontend_dsn'] !== '' || $config['frontend_script'] !== '');

        return [
            'enabled' => $frontendEnabled,
            'dsn' => $frontendEnabled ? $config['frontend_dsn'] : '',
            'environment' => $config['environment'],
            'release' => $config['release'],
            'script' => $frontendEnabled ? $config['frontend_script'] : '',
            'traces_sample_rate' => $config['frontend_traces_sample_rate'],
            'replays_session_sample_rate' => $config['frontend_replays_session_sample_rate'],
            'replays_on_error_sample_rate' => $config['frontend_replays_on_error_sample_rate'],
        ];
    }

    public function isEnabled(): bool
    {
        $config = $this->get();

        return $config['enabled'] && $config['dsn'] !== '';
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function save(array $attributes): array
    {
        $settings = GeneralSetting::query()->first();

        if (! $settings instanceof GeneralSetting) {
            $settings = GeneralSetting::query()->create([
                'site_name' => (string) config('app.name', 'KoAkademy'),
            ]);
        }

        $sanitized = $this->sanitize($attributes);
        $moreConfigs = is_array($settings->more_configs) ? $settings->more_configs : [];
        $errorReporting = is_array($moreConfigs['error_reporting'] ?? null) ? $moreConfigs['error_reporting'] : [];
        $storedProviders = is_array($errorReporting['providers'] ?? null) ? $errorReporting['providers'] : [];
        $storedProviders['sentry'] = $sanitized;
        $errorReporting['providers'] = $storedProviders;
        $moreConfigs['error_reporting'] = $errorReporting;

        // Drop the legacy single-provider row once migrated.
        unset($moreConfigs['sentry']);

        $settings->update(['more_configs' => $moreConfigs]);
        GeneralSetting::clearCache();

        $this->applyToConfig($sanitized);

        return $this->get();
    }

    /**
     * Push the resolved Sentry configuration into Laravel's runtime config.
     * Called on boot (best-effort) and immediately after admin saves.
     *
     * @param  array<string, mixed>|null  $override
     */
    public function applyToConfig(?array $override = null): void
    {
        try {
            $config = $override !== null ? array_merge($this->get(), $this->sanitize($override)) : $this->get();
        } catch (Throwable) {
            return;
        }

        if (! $config['enabled'] || $config['dsn'] === '') {
            config(['sentry.dsn' => null]);

            return;
        }

        config([
            'sentry.dsn' => $config['dsn'],
            'sentry.environment' => $config['environment'] !== '' ? $config['environment'] : null,
            'sentry.release' => $config['release'] !== '' ? $config['release'] : null,
            'sentry.sample_rate' => $config['sample_rate'],
            'sentry.traces_sample_rate' => $config['traces_sample_rate'],
            'sentry.profiles_sample_rate' => $config['profiles_sample_rate'],
            'sentry.send_default_pii' => $config['send_default_pii'],
            'sentry.enable_logs' => $config['enable_logs'],
        ]);
    }

    /**
     * Sentry browser snippet injected into <head> when the frontend
     * integration is enabled. Uses the official versioned browser bundle so
     * no npm dependency is required; a manual override snippet (set in the
     * admin panel) takes precedence when present.
     */
    public function renderHeadMarkup(): string
    {
        $frontend = $this->frontendConfig();

        if (! $frontend['enabled']) {
            return '';
        }

        if ($frontend['script'] !== '') {
            return $frontend['script'];
        }

        if ($frontend['dsn'] === '') {
            return '';
        }

        $dsn = htmlspecialchars($frontend['dsn'], ENT_QUOTES, 'UTF-8');
        $environment = htmlspecialchars($frontend['environment'], ENT_QUOTES, 'UTF-8');
        $release = htmlspecialchars($frontend['release'], ENT_QUOTES, 'UTF-8');
        $traces = json_encode($frontend['traces_sample_rate']);
        $replaysSession = json_encode($frontend['replays_session_sample_rate']);
        $replaysOnError = json_encode($frontend['replays_on_error_sample_rate']);

        // Pinned Sentry JS bundle; bump when upgrading the browser SDK.
        // @see https://docs.sentry.io/platforms/javascript/install/cdn/
        return <<<HTML
<script src="https://browser.sentry-cdn.com/9.18.0/bundle.tracing.replay.min.js" crossorigin="anonymous"></script>
<script>
window.Sentry && window.Sentry.init({
  dsn: "{$dsn}",
  environment: "{$environment}",
  release: "{$release}" || undefined,
  tracesSampleRate: {$traces},
  replaysSessionSampleRate: {$replaysSession},
  replaysOnErrorSampleRate: {$replaysOnError}
});
</script>
HTML;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function sanitize(array $attributes): array
    {
        $dsn = $this->normalizeString($attributes['dsn'] ?? '');
        $frontendDsn = $this->normalizeString($attributes['frontend_dsn'] ?? '');

        return [
            'enabled' => (bool) ($attributes['enabled'] ?? false),
            'dsn' => mb_substr($dsn, 0, 2048),
            'environment' => mb_substr($this->normalizeString($attributes['environment'] ?? 'production') ?: 'production', 0, 64),
            'release' => mb_substr($this->normalizeString($attributes['release'] ?? ''), 0, 255),
            'sample_rate' => $this->clampFloat($attributes['sample_rate'] ?? 1.0, 0.0, 1.0),
            'traces_sample_rate' => $this->clampFloat($attributes['traces_sample_rate'] ?? 0.2, 0.0, 1.0),
            'profiles_sample_rate' => array_key_exists('profiles_sample_rate', $attributes) && $attributes['profiles_sample_rate'] !== null && $attributes['profiles_sample_rate'] !== ''
                ? $this->clampFloat($attributes['profiles_sample_rate'], 0.0, 1.0)
                : null,
            'send_default_pii' => (bool) ($attributes['send_default_pii'] ?? false),
            'enable_logs' => (bool) ($attributes['enable_logs'] ?? false),
            'frontend_enabled' => (bool) ($attributes['frontend_enabled'] ?? false),
            'frontend_dsn' => mb_substr($frontendDsn !== '' ? $frontendDsn : $dsn, 0, 2048),
            'frontend_script' => mb_substr($this->normalizeString($attributes['frontend_script'] ?? ''), 0, 20000),
            'frontend_traces_sample_rate' => $this->clampFloat($attributes['frontend_traces_sample_rate'] ?? 0.1, 0.0, 1.0),
            'frontend_replays_session_sample_rate' => $this->clampFloat($attributes['frontend_replays_session_sample_rate'] ?? 0.0, 0.0, 1.0),
            'frontend_replays_on_error_sample_rate' => $this->clampFloat($attributes['frontend_replays_on_error_sample_rate'] ?? 1.0, 0.0, 1.0),
        ];
    }

    /** @return array<string, mixed> */
    private function savedConfig(): array
    {
        try {
            if (! Schema::hasTable('general_settings')) {
                return [];
            }

            $moreConfigs = GeneralSetting::query()->first()?->more_configs;

            if (! is_array($moreConfigs)) {
                return [];
            }

            $errorReporting = $moreConfigs['error_reporting'] ?? null;
            $providers = is_array($errorReporting) ? ($errorReporting['providers'] ?? null) : null;
            $saved = is_array($providers) ? ($providers['sentry'] ?? null) : null;

            if (is_array($saved)) {
                return $saved;
            }

            // Legacy single-provider row (pre multi-provider settings).
            $legacy = $moreConfigs[self::CONFIG_KEY] ?? [];

            return is_array($legacy) ? $legacy : [];
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

    private function resolveFloat(mixed $value, float $fallback, float $min, float $max): float
    {
        if ($value === null || $value === '') {
            return $this->clampFloat($fallback, $min, $max);
        }

        return $this->clampFloat($value, $min, $max);
    }

    private function resolveNullableFloat(mixed $value, ?float $fallback, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return $this->clampFloat($value, $min, $max);
    }

    private function clampFloat(mixed $value, float $min, float $max): float
    {
        $float = is_numeric($value) ? (float) $value : $min;

        return min($max, max($min, $float));
    }

    private function envFloat(string $key, float $fallback): float
    {
        $raw = env($key);

        if ($raw === null || $raw === '') {
            return $fallback;
        }

        return is_numeric($raw) ? (float) $raw : $fallback;
    }

    private function envNullableFloat(string $key): ?float
    {
        $raw = env($key);

        if ($raw === null || $raw === '') {
            return null;
        }

        return is_numeric($raw) ? (float) $raw : null;
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
