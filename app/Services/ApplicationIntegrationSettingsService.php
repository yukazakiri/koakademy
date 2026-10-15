<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Arr;

final class ApplicationIntegrationSettingsService
{
    public const CONFIG_KEY = 'application_integrations';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedConfig = null;

    /**
     * @return array{
     *     google_calendar: array{
     *         enabled: bool,
     *         client_id: string|null,
     *         client_secret: string|null,
     *         redirect_uri: string|null,
     *         auto_sync: bool,
     *     },
     *     calendar_feed: array{
     *         enabled: bool,
     *         students_enabled: bool,
     *         faculty_enabled: bool,
     *         title_prefix: string|null,
     *         rrule_weeks: int,
     *     },
     *     microsoft: array{
     *         enabled: bool,
     *         client_id: string|null,
     *         client_secret: string|null,
     *         tenant_id: string|null,
     *         redirect_uri: string|null,
     *     },
     *     cloud_storage: array{
     *         google_drive_enabled: bool,
     *         google_drive_api_key: string|null,
     *         google_drive_client_id: string|null,
     *         onedrive_enabled: bool,
     *         onedrive_client_id: string|null,
     *     },
     *     conferencing_and_lms: array{
     *         zoom_enabled: bool,
     *         zoom_account_id: string|null,
     *         zoom_client_id: string|null,
     *         zoom_client_secret: string|null,
     *         canvas_enabled: bool,
     *         canvas_base_url: string|null,
     *         canvas_client_id: string|null,
     *     }
     * }
     */
    public function get(): array
    {
        if ($this->cachedConfig !== null) {
            return $this->cachedConfig;
        }

        $settings = GeneralSetting::query()->first();
        $stored = $settings?->more_configs[self::CONFIG_KEY] ?? [];
        $social = $settings?->social_network ?? [];

        $defaultGoogleClientId = Arr::get($stored, 'google_calendar.client_id')
            ?: (Arr::get($social, 'google_client_id') ?: (string) config('services.google.client_id'));

        $defaultGoogleClientSecret = Arr::get($stored, 'google_calendar.client_secret')
            ?: (Arr::get($social, 'google_client_secret') ?: (string) config('services.google.client_secret'));

        $defaultGoogleRedirect = Arr::get($stored, 'google_calendar.redirect_uri')
            ?: (Arr::get($social, 'google_redirect_uri') ?: (string) config('services.google.redirect', url('/integrations/google/callback')));

        $appName = (string) config('app.name', 'KoAkademy');

        $config = [
            'google_calendar' => [
                'enabled' => (bool) Arr::get($stored, 'google_calendar.enabled', true),
                'client_id' => $defaultGoogleClientId ?: null,
                'client_secret' => $defaultGoogleClientSecret ?: null,
                'redirect_uri' => $defaultGoogleRedirect ?: null,
                'auto_sync' => (bool) Arr::get($stored, 'google_calendar.auto_sync', true),
            ],
            'calendar_feed' => [
                'enabled' => (bool) Arr::get($stored, 'calendar_feed.enabled', true),
                'students_enabled' => (bool) Arr::get($stored, 'calendar_feed.students_enabled', true),
                'faculty_enabled' => (bool) Arr::get($stored, 'calendar_feed.faculty_enabled', true),
                'title_prefix' => (string) Arr::get($stored, 'calendar_feed.title_prefix', $appName),
                'rrule_weeks' => (int) Arr::get($stored, 'calendar_feed.rrule_weeks', 18),
            ],
            'microsoft' => [
                'enabled' => (bool) Arr::get($stored, 'microsoft.enabled', false),
                'client_id' => (string) Arr::get($stored, 'microsoft.client_id', (string) config('services.microsoft.client_id', '')),
                'client_secret' => (string) Arr::get($stored, 'microsoft.client_secret', (string) config('services.microsoft.client_secret', '')),
                'tenant_id' => (string) Arr::get($stored, 'microsoft.tenant_id', (string) config('services.microsoft.tenant_id', 'common')),
                'redirect_uri' => (string) Arr::get($stored, 'microsoft.redirect_uri', url('/integrations/microsoft/callback')),
            ],
            'cloud_storage' => [
                'google_drive_enabled' => (bool) Arr::get($stored, 'cloud_storage.google_drive_enabled', false),
                'google_drive_api_key' => (string) Arr::get($stored, 'cloud_storage.google_drive_api_key', ''),
                'google_drive_client_id' => (string) Arr::get($stored, 'cloud_storage.google_drive_client_id', ''),
                'onedrive_enabled' => (bool) Arr::get($stored, 'cloud_storage.onedrive_enabled', false),
                'onedrive_client_id' => (string) Arr::get($stored, 'cloud_storage.onedrive_client_id', ''),
            ],
            'conferencing_and_lms' => [
                'zoom_enabled' => (bool) Arr::get($stored, 'conferencing_and_lms.zoom_enabled', false),
                'zoom_account_id' => (string) Arr::get($stored, 'conferencing_and_lms.zoom_account_id', ''),
                'zoom_client_id' => (string) Arr::get($stored, 'conferencing_and_lms.zoom_client_id', ''),
                'zoom_client_secret' => (string) Arr::get($stored, 'conferencing_and_lms.zoom_client_secret', ''),
                'canvas_enabled' => (bool) Arr::get($stored, 'conferencing_and_lms.canvas_enabled', false),
                'canvas_base_url' => (string) Arr::get($stored, 'conferencing_and_lms.canvas_base_url', ''),
                'canvas_client_id' => (string) Arr::get($stored, 'conferencing_and_lms.canvas_client_id', ''),
            ],
        ];

        return $this->cachedConfig = $config;
    }

    /**
     * Update runtime integrations configuration.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(array $attributes): void
    {
        $settings = GeneralSetting::query()->firstOrCreate([], ['site_name' => config('app.name')]);
        $moreConfigs = is_array($settings->more_configs) ? $settings->more_configs : [];

        $sanitized = [
            'google_calendar' => [
                'enabled' => filter_var(Arr::get($attributes, 'google_calendar.enabled', true), FILTER_VALIDATE_BOOLEAN),
                'client_id' => mb_trim((string) Arr::get($attributes, 'google_calendar.client_id', '')) ?: null,
                'client_secret' => mb_trim((string) Arr::get($attributes, 'google_calendar.client_secret', '')) ?: null,
                'redirect_uri' => mb_trim((string) Arr::get($attributes, 'google_calendar.redirect_uri', '')) ?: null,
                'auto_sync' => filter_var(Arr::get($attributes, 'google_calendar.auto_sync', true), FILTER_VALIDATE_BOOLEAN),
            ],
            'calendar_feed' => [
                'enabled' => filter_var(Arr::get($attributes, 'calendar_feed.enabled', true), FILTER_VALIDATE_BOOLEAN),
                'students_enabled' => filter_var(Arr::get($attributes, 'calendar_feed.students_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'faculty_enabled' => filter_var(Arr::get($attributes, 'calendar_feed.faculty_enabled', true), FILTER_VALIDATE_BOOLEAN),
                'title_prefix' => mb_trim((string) Arr::get($attributes, 'calendar_feed.title_prefix', '')) ?: config('app.name'),
                'rrule_weeks' => max(1, min(52, (int) Arr::get($attributes, 'calendar_feed.rrule_weeks', 18))),
            ],
            'microsoft' => [
                'enabled' => filter_var(Arr::get($attributes, 'microsoft.enabled', false), FILTER_VALIDATE_BOOLEAN),
                'client_id' => mb_trim((string) Arr::get($attributes, 'microsoft.client_id', '')) ?: null,
                'client_secret' => mb_trim((string) Arr::get($attributes, 'microsoft.client_secret', '')) ?: null,
                'tenant_id' => mb_trim((string) Arr::get($attributes, 'microsoft.tenant_id', 'common')) ?: 'common',
                'redirect_uri' => mb_trim((string) Arr::get($attributes, 'microsoft.redirect_uri', '')) ?: null,
            ],
            'cloud_storage' => [
                'google_drive_enabled' => filter_var(Arr::get($attributes, 'cloud_storage.google_drive_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'google_drive_api_key' => mb_trim((string) Arr::get($attributes, 'cloud_storage.google_drive_api_key', '')) ?: null,
                'google_drive_client_id' => mb_trim((string) Arr::get($attributes, 'cloud_storage.google_drive_client_id', '')) ?: null,
                'onedrive_enabled' => filter_var(Arr::get($attributes, 'cloud_storage.onedrive_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'onedrive_client_id' => mb_trim((string) Arr::get($attributes, 'cloud_storage.onedrive_client_id', '')) ?: null,
            ],
            'conferencing_and_lms' => [
                'zoom_enabled' => filter_var(Arr::get($attributes, 'conferencing_and_lms.zoom_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'zoom_account_id' => mb_trim((string) Arr::get($attributes, 'conferencing_and_lms.zoom_account_id', '')) ?: null,
                'zoom_client_id' => mb_trim((string) Arr::get($attributes, 'conferencing_and_lms.zoom_client_id', '')) ?: null,
                'zoom_client_secret' => mb_trim((string) Arr::get($attributes, 'conferencing_and_lms.zoom_client_secret', '')) ?: null,
                'canvas_enabled' => filter_var(Arr::get($attributes, 'conferencing_and_lms.canvas_enabled', false), FILTER_VALIDATE_BOOLEAN),
                'canvas_base_url' => mb_trim((string) Arr::get($attributes, 'conferencing_and_lms.canvas_base_url', '')) ?: null,
                'canvas_client_id' => mb_trim((string) Arr::get($attributes, 'conferencing_and_lms.canvas_client_id', '')) ?: null,
            ],
        ];

        $moreConfigs[self::CONFIG_KEY] = $sanitized;
        $settings->update(['more_configs' => $moreConfigs]);
        $this->cachedConfig = null;
        GeneralSetting::clearCache();

        $this->applyRuntimeConfig();
    }

    /**
     * Apply runtime configuration to Laravel config repository.
     */
    public function applyRuntimeConfig(): void
    {
        $config = $this->get();

        if (filled(Arr::get($config, 'google_calendar.client_id'))) {
            config(['services.google.client_id' => Arr::get($config, 'google_calendar.client_id')]);
        }

        if (filled(Arr::get($config, 'google_calendar.client_secret'))) {
            config(['services.google.client_secret' => Arr::get($config, 'google_calendar.client_secret')]);
        }

        if (filled(Arr::get($config, 'google_calendar.redirect_uri'))) {
            config(['services.google.redirect' => Arr::get($config, 'google_calendar.redirect_uri')]);
        }

        if (filled(Arr::get($config, 'microsoft.client_id'))) {
            config([
                'services.microsoft.client_id' => Arr::get($config, 'microsoft.client_id'),
                'services.microsoft.client_secret' => Arr::get($config, 'microsoft.client_secret'),
                'services.microsoft.tenant_id' => Arr::get($config, 'microsoft.tenant_id', 'common'),
                'services.microsoft.redirect' => Arr::get($config, 'microsoft.redirect_uri'),
            ]);
        }
    }

    public function isGoogleCalendarEnabled(): bool
    {
        return (bool) Arr::get($this->get(), 'google_calendar.enabled', true);
    }

    public function isCalendarFeedEnabled(): bool
    {
        return (bool) Arr::get($this->get(), 'calendar_feed.enabled', true);
    }

    public function isCalendarFeedAllowedFor(?User $user): bool
    {
        if (! $this->isCalendarFeedEnabled()) {
            return false;
        }

        if (! $user) {
            return false;
        }

        if ($user->isStudentRole()) {
            return (bool) Arr::get($this->get(), 'calendar_feed.students_enabled', true);
        }

        if ($user->isFaculty()) {
            return (bool) Arr::get($this->get(), 'calendar_feed.faculty_enabled', true);
        }

        return true;
    }

    public function getCalendarFeedTitlePrefix(): string
    {
        $prefix = (string) Arr::get($this->get(), 'calendar_feed.title_prefix');

        return filled($prefix) ? $prefix : (string) config('app.name', 'KoAkademy');
    }

    public function getCalendarFeedRruleWeeks(): int
    {
        return (int) Arr::get($this->get(), 'calendar_feed.rrule_weeks', 18);
    }

    public function isMicrosoftEnabled(): bool
    {
        return (bool) Arr::get($this->get(), 'microsoft.enabled', false);
    }

    public function isGoogleDriveEnabled(): bool
    {
        return (bool) Arr::get($this->get(), 'cloud_storage.google_drive_enabled', false);
    }

    public function isOneDriveEnabled(): bool
    {
        return (bool) Arr::get($this->get(), 'cloud_storage.onedrive_enabled', false);
    }
}
