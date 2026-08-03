<?php

declare(strict_types=1);

namespace App\Services\Newsletter;

use App\Enums\NewsletterProvider;
use App\Models\GeneralSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

final class NewsletterSettingsService
{
    private const string LOOKUP_CACHE_VERSION_KEY = 'newsletter:lookup-cache-version';

    /** @return array<string, mixed> */
    public function get(): array
    {
        $stored = GeneralSetting::query()->first()?->newsletter_settings;

        return $this->normalize(is_array($stored) ? $stored : []);
    }

    /** @return array<string, mixed> */
    public function forAdministration(): array
    {
        $settings = $this->get();

        return [
            'enabled' => (bool) $settings['enabled'],
            'provider' => $settings['provider'],
            'providers' => [
                'sequenzy' => [
                    'configured' => filled(data_get($settings, 'providers.sequenzy.api_key')),
                ],
                'brevo' => [
                    'configured' => filled(data_get($settings, 'providers.brevo.api_key')) && filled(data_get($settings, 'providers.brevo.list_id')),
                    'list_id' => (string) data_get($settings, 'providers.brevo.list_id', ''),
                ],
                'mailchimp' => [
                    'configured' => filled(data_get($settings, 'providers.mailchimp.api_key'))
                        && filled(data_get($settings, 'providers.mailchimp.server_prefix'))
                        && filled(data_get($settings, 'providers.mailchimp.audience_id')),
                    'server_prefix' => (string) data_get($settings, 'providers.mailchimp.server_prefix', ''),
                    'audience_id' => (string) data_get($settings, 'providers.mailchimp.audience_id', ''),
                ],
            ],
        ];
    }

    /**
     * Merge a validated form payload with stored secrets. Blank secret fields
     * intentionally preserve the encrypted value already in the database.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function merge(array $validated): array
    {
        $settings = $this->get();
        $provider = NewsletterProvider::from((string) $validated['provider']);
        $settings['enabled'] = (bool) $validated['enabled'];
        $settings['provider'] = $provider->value;

        foreach (NewsletterProvider::cases() as $candidate) {
            $incoming = Arr::get($validated, 'providers.'.$candidate->value, []);
            if (! is_array($incoming)) {
                continue;
            }
            foreach ($incoming as $key => $value) {
                if ($key === 'api_key' && blank($value)) {
                    continue;
                }
                $settings['providers'][$candidate->value][$key] = is_string($value) ? mb_trim($value) : $value;
            }
        }

        return $this->normalize($settings);
    }

    /** @param array<string, mixed> $settings */
    public function save(array $settings): void
    {
        $model = GeneralSetting::query()->firstOrCreate([], ['site_name' => (string) config('app.name', 'KoAkademy')]);
        $previousProvider = data_get($model->newsletter_settings, 'provider');
        $model->update(['newsletter_settings' => $this->normalize($settings)]);

        if ($previousProvider !== $settings['provider']) {
            $this->clearLookupCaches();
        }
    }

    public function clearLookupCaches(): void
    {
        Cache::forever(self::LOOKUP_CACHE_VERSION_KEY, ((int) Cache::get(self::LOOKUP_CACHE_VERSION_KEY, 1)) + 1);
    }

    public function lookupCacheVersion(): int
    {
        return (int) Cache::get(self::LOOKUP_CACHE_VERSION_KEY, 1);
    }

    /** @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalize(array $settings): array
    {
        return array_replace_recursive([
            'enabled' => false,
            'provider' => NewsletterProvider::Sequenzy->value,
            'providers' => [
                'sequenzy' => ['api_key' => ''],
                'brevo' => ['api_key' => '', 'list_id' => ''],
                'mailchimp' => ['api_key' => '', 'server_prefix' => '', 'audience_id' => ''],
            ],
        ], $settings);
    }
}
