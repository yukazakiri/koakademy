<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;

final class SocialiteProviderService
{
    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedConfig = null;

    /**
     * @return array<string, array{label: string, env_prefix: string}>
     */
    public function providers(): array
    {
        return [
            'google' => ['label' => 'Google', 'env_prefix' => 'GOOGLE'],
            'facebook' => ['label' => 'Facebook', 'env_prefix' => 'FACEBOOK'],
            'github' => ['label' => 'GitHub', 'env_prefix' => 'GITHUB'],
            'twitter' => ['label' => 'X / Twitter', 'env_prefix' => 'TWITTER'],
            'linkedin' => ['label' => 'LinkedIn', 'env_prefix' => 'LINKEDIN'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function config(): array
    {
        if ($this->cachedConfig !== null) {
            return $this->cachedConfig;
        }

        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
        $stored = $settings?->social_network ?? [];

        return $this->cachedConfig = array_merge($this->defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @return array<int, array{key: string, label: string, redirect_url: string}>
     */
    public function enabledProviders(): array
    {
        return collect($this->providers())
            ->filter(fn (array $provider, string $key): bool => $this->isEnabled($key))
            ->map(fn (array $provider, string $key): array => [
                'key' => $key,
                'label' => $provider['label'],
                'redirect_url' => route('social.auth.redirect', ['provider' => $key]),
            ])
            ->values()
            ->all();
    }

    public function isSupported(string $provider): bool
    {
        return array_key_exists($provider, $this->providers());
    }

    public function isConfigured(string $provider): bool
    {
        $config = $this->config();

        return filled(Arr::get($config, "{$provider}_client_id"))
            && filled(Arr::get($config, "{$provider}_client_secret"));
    }

    public function isEnabled(string $provider): bool
    {
        $config = $this->config();

        return $this->isSupported($provider)
            && $this->isConfigured($provider)
            && (bool) Arr::get($config, "{$provider}_enabled", false);
    }

    public function applyRuntimeConfig(string $provider, ?string $redirectUrl = null): void
    {
        $config = $this->config();

        config([
            "services.{$provider}.client_id" => Arr::get($config, "{$provider}_client_id"),
            "services.{$provider}.client_secret" => Arr::get($config, "{$provider}_client_secret"),
            "services.{$provider}.redirect" => $redirectUrl
                ?? Arr::get($config, "{$provider}_redirect_uri")
                ?? url("/auth/{$provider}/callback"),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        $defaults = [];

        foreach (array_keys($this->providers()) as $provider) {
            $defaults["{$provider}_client_id"] = config("services.{$provider}.client_id");
            $defaults["{$provider}_client_secret"] = config("services.{$provider}.client_secret");
            $defaults["{$provider}_enabled"] = false;
            $defaults["{$provider}_redirect_uri"] = config("services.{$provider}.redirect")
                ?: url("/auth/{$provider}/callback");
        }

        return $defaults;
    }
}
