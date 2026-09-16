<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\GeneralSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class AiSettingsService
{
    public const string CACHE_KEY = 'ai:settings:normalized';

    /**
     * @return array<string, array{label: string, driver: string, default_url: string|null, requires_key: bool, supports_model_fetch: bool}>
     */
    public static function supportedProviders(): array
    {
        return [
            'anthropic' => [
                'label' => 'Anthropic (Claude)',
                'driver' => 'anthropic',
                'default_url' => 'https://api.anthropic.com/v1',
                'requires_key' => true,
                'supports_model_fetch' => true,
            ],
            'openai' => [
                'label' => 'OpenAI',
                'driver' => 'openai',
                'default_url' => 'https://api.openai.com/v1',
                'requires_key' => true,
                'supports_model_fetch' => true,
            ],
            'gemini' => [
                'label' => 'Google Gemini',
                'driver' => 'gemini',
                'default_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'requires_key' => true,
                'supports_model_fetch' => true,
            ],
            'groq' => [
                'label' => 'Groq',
                'driver' => 'groq',
                'default_url' => 'https://api.groq.com/openai/v1',
                'requires_key' => true,
                'supports_model_fetch' => true,
            ],
            'deepseek' => [
                'label' => 'DeepSeek',
                'driver' => 'deepseek',
                'default_url' => 'https://api.deepseek.com',
                'requires_key' => true,
                'supports_model_fetch' => true,
            ],
            'mistral' => [
                'label' => 'Mistral AI',
                'driver' => 'mistral',
                'default_url' => 'https://api.mistral.ai/v1',
                'requires_key' => true,
                'supports_model_fetch' => true,
            ],
            'openrouter' => [
                'label' => 'OpenRouter',
                'driver' => 'openrouter',
                'default_url' => 'https://openrouter.ai/api/v1',
                'requires_key' => true,
                'supports_model_fetch' => true,
            ],
            'ollama' => [
                'label' => 'Ollama (Self-Hosted)',
                'driver' => 'ollama',
                'default_url' => 'http://localhost:11434',
                'requires_key' => false,
                'supports_model_fetch' => true,
            ],
            'openai-compatible' => [
                'label' => 'OpenAI-Compatible (Custom / vLLM / LM Studio)',
                'driver' => 'openai-compatible',
                'default_url' => '',
                'requires_key' => false,
                'supports_model_fetch' => true,
            ],
        ];
    }

    /**
     * Retrieve the stored AI settings.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        $stored = GeneralSetting::query()->first()?->ai_settings;

        return $this->normalize(is_array($stored) ? $stored : []);
    }

    /**
     * Default settings blueprint.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $providers = [];

        foreach (self::supportedProviders() as $key => $meta) {
            $providers[$key] = [
                'enabled' => $key === 'anthropic' || $key === 'openai',
                'api_key' => '',
                'base_url' => $meta['default_url'] ?? '',
                'default_chat_model' => match ($key) {
                    'anthropic' => 'claude-3-7-sonnet-20250219',
                    'openai' => 'gpt-4o',
                    'gemini' => 'gemini-2.0-flash',
                    'groq' => 'llama-3.3-70b-versatile',
                    'deepseek' => 'deepseek-chat',
                    'mistral' => 'mistral-large-latest',
                    'openrouter' => 'anthropic/claude-3.7-sonnet',
                    'ollama' => 'llama3:latest',
                    default => '',
                },
                'default_fast_model' => match ($key) {
                    'anthropic' => 'claude-3-5-haiku-20241022',
                    'openai' => 'gpt-4o-mini',
                    'gemini' => 'gemini-2.0-flash-lite',
                    'groq' => 'llama-3.1-8b-instant',
                    'deepseek' => 'deepseek-chat',
                    'mistral' => 'mistral-small-latest',
                    default => '',
                },
                'default_embeddings_model' => match ($key) {
                    'openai' => 'text-embedding-3-small',
                    'gemini' => 'text-embedding-004',
                    default => '',
                },
                'custom_models' => [],
                'discovered_models' => [],
                'last_fetched_at' => null,
            ];
        }

        return [
            'enabled' => true,
            'primary_provider' => 'anthropic',
            'fallback_provider' => 'openai',
            'failover_enabled' => true,
            'request_timeout_seconds' => 60,
            'providers' => $providers,
            'custom_providers' => [],
        ];
    }

    /**
     * Prepare settings safely for administration UI without exposing full API keys.
     *
     * @return array<string, mixed>
     */
    public function forAdministration(): array
    {
        $settings = $this->get();
        $adminProviders = [];

        foreach (self::supportedProviders() as $key => $meta) {
            $current = $settings['providers'][$key] ?? [];
            $rawKey = (string) ($current['api_key'] ?? '');
            $hasKey = filled($rawKey);

            $adminProviders[$key] = [
                'key' => $key,
                'label' => $meta['label'],
                'driver' => $meta['driver'],
                'enabled' => (bool) ($current['enabled'] ?? false),
                'configured' => $hasKey || ! $meta['requires_key'],
                'api_key_masked' => $hasKey ? $this->maskSecret($rawKey) : '',
                'base_url' => (string) ($current['base_url'] ?? ($meta['default_url'] ?? '')),
                'default_chat_model' => (string) ($current['default_chat_model'] ?? ''),
                'default_fast_model' => (string) ($current['default_fast_model'] ?? ''),
                'default_embeddings_model' => (string) ($current['default_embeddings_model'] ?? ''),
                'custom_models' => is_array($current['custom_models'] ?? null) ? $current['custom_models'] : [],
                'discovered_models' => is_array($current['discovered_models'] ?? null) ? $current['discovered_models'] : [],
                'last_fetched_at' => $current['last_fetched_at'] ?? null,
                'requires_key' => $meta['requires_key'],
                'supports_model_fetch' => $meta['supports_model_fetch'],
                'is_custom' => false,
            ];
        }

        $adminCustomProviders = [];

        foreach ($settings['custom_providers'] ?? [] as $customKey => $custom) {
            if (! is_array($custom)) {
                continue;
            }

            $rawKey = (string) ($custom['api_key'] ?? '');
            $hasKey = filled($rawKey);

            $adminCustomProviders[$customKey] = [
                'key' => $customKey,
                'label' => (string) ($custom['label'] ?? $customKey),
                'driver' => 'openai-compatible',
                'enabled' => (bool) ($custom['enabled'] ?? true),
                'configured' => $hasKey || ! (bool) ($custom['requires_key'] ?? false),
                'api_key_masked' => $hasKey ? $this->maskSecret($rawKey) : '',
                'base_url' => (string) ($custom['base_url'] ?? ''),
                'headers' => is_array($custom['headers'] ?? null) ? $custom['headers'] : [],
                'default_chat_model' => (string) ($custom['default_chat_model'] ?? ''),
                'default_fast_model' => (string) ($custom['default_fast_model'] ?? ''),
                'default_embeddings_model' => (string) ($custom['default_embeddings_model'] ?? ''),
                'custom_models' => is_array($custom['custom_models'] ?? null) ? $custom['custom_models'] : [],
                'discovered_models' => is_array($custom['discovered_models'] ?? null) ? $custom['discovered_models'] : [],
                'last_fetched_at' => $custom['last_fetched_at'] ?? null,
                'requires_key' => (bool) ($custom['requires_key'] ?? false),
                'supports_model_fetch' => true,
                'is_custom' => true,
            ];
        }

        return [
            'enabled' => (bool) $settings['enabled'],
            'primary_provider' => (string) $settings['primary_provider'],
            'fallback_provider' => (string) ($settings['fallback_provider'] ?? ''),
            'failover_enabled' => (bool) ($settings['failover_enabled'] ?? true),
            'request_timeout_seconds' => (int) ($settings['request_timeout_seconds'] ?? 60),
            'providers' => $adminProviders,
            'custom_providers' => $adminCustomProviders,
        ];
    }

    /**
     * Merge incoming settings with stored settings, preserving secrets when left blank.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function merge(array $validated): array
    {
        $settings = $this->get();

        if (array_key_exists('enabled', $validated)) {
            $settings['enabled'] = (bool) $validated['enabled'];
        }

        if (array_key_exists('primary_provider', $validated) && filled($validated['primary_provider'])) {
            $settings['primary_provider'] = (string) $validated['primary_provider'];
        }

        if (array_key_exists('fallback_provider', $validated)) {
            $settings['fallback_provider'] = filled($validated['fallback_provider']) ? (string) $validated['fallback_provider'] : null;
        }

        if (array_key_exists('failover_enabled', $validated)) {
            $settings['failover_enabled'] = (bool) $validated['failover_enabled'];
        }

        if (array_key_exists('request_timeout_seconds', $validated)) {
            $settings['request_timeout_seconds'] = max(5, (int) $validated['request_timeout_seconds']);
        }

        $incomingProviders = Arr::get($validated, 'providers', []);

        if (is_array($incomingProviders)) {
            foreach (self::supportedProviders() as $providerKey => $meta) {
                if (! isset($incomingProviders[$providerKey]) || ! is_array($incomingProviders[$providerKey])) {
                    continue;
                }

                $incoming = $incomingProviders[$providerKey];
                $existing = $settings['providers'][$providerKey] ?? [];

                if (array_key_exists('enabled', $incoming)) {
                    $existing['enabled'] = (bool) $incoming['enabled'];
                }

                // Preserve existing API key if incoming is blank or omitted
                if (array_key_exists('api_key', $incoming) && filled($incoming['api_key'])) {
                    $existing['api_key'] = mb_trim((string) $incoming['api_key']);
                }

                if (array_key_exists('base_url', $incoming)) {
                    $existing['base_url'] = mb_trim((string) $incoming['base_url']);
                }

                if (array_key_exists('default_chat_model', $incoming)) {
                    $existing['default_chat_model'] = mb_trim((string) $incoming['default_chat_model']);
                }

                if (array_key_exists('default_fast_model', $incoming)) {
                    $existing['default_fast_model'] = mb_trim((string) $incoming['default_fast_model']);
                }

                if (array_key_exists('default_embeddings_model', $incoming)) {
                    $existing['default_embeddings_model'] = mb_trim((string) $incoming['default_embeddings_model']);
                }

                if (array_key_exists('custom_models', $incoming) && is_array($incoming['custom_models'])) {
                    $existing['custom_models'] = array_values(array_unique(array_filter(
                        array_map('trim', $incoming['custom_models']),
                        fn ($model): bool => filled($model) && is_string($model)
                    )));
                }

                $settings['providers'][$providerKey] = $existing;
            }
        }

        // Process custom OpenAI-compatible providers
        if (array_key_exists('custom_providers', $validated) && is_array($validated['custom_providers'])) {
            $existingCustom = $settings['custom_providers'] ?? [];
            $newCustom = [];

            foreach ($validated['custom_providers'] as $item) {
                if (! is_array($item) || blank($item['key'] ?? null)) {
                    continue;
                }

                $key = mb_trim((string) $item['key']);
                $prev = $existingCustom[$key] ?? [];

                $storedKey = (array_key_exists('api_key', $item) && filled($item['api_key']))
                    ? mb_trim((string) $item['api_key'])
                    : ($prev['api_key'] ?? '');

                $newCustom[$key] = [
                    'key' => $key,
                    'label' => filled($item['label'] ?? null) ? mb_trim((string) $item['label']) : $key,
                    'driver' => 'openai-compatible',
                    'enabled' => (bool) ($item['enabled'] ?? true),
                    'api_key' => $storedKey,
                    'base_url' => mb_trim((string) ($item['base_url'] ?? ($prev['base_url'] ?? ''))),
                    'headers' => is_array($item['headers'] ?? null) ? $item['headers'] : ($prev['headers'] ?? []),
                    'default_chat_model' => mb_trim((string) ($item['default_chat_model'] ?? ($prev['default_chat_model'] ?? ''))),
                    'default_fast_model' => mb_trim((string) ($item['default_fast_model'] ?? ($prev['default_fast_model'] ?? ''))),
                    'default_embeddings_model' => mb_trim((string) ($item['default_embeddings_model'] ?? ($prev['default_embeddings_model'] ?? ''))),
                    'custom_models' => array_values(array_unique(array_filter(
                        array_map('trim', is_array($item['custom_models'] ?? null) ? $item['custom_models'] : ($prev['custom_models'] ?? [])),
                        fn ($model): bool => filled($model) && is_string($model)
                    ))),
                    'discovered_models' => is_array($prev['discovered_models'] ?? null) ? $prev['discovered_models'] : [],
                    'last_fetched_at' => $prev['last_fetched_at'] ?? null,
                    'requires_key' => (bool) ($item['requires_key'] ?? false),
                ];
            }

            $settings['custom_providers'] = $newCustom;
        }

        $normalized = $this->normalize($settings);
        $this->save($normalized);

        return $normalized;
    }

    /**
     * Save updated discovered models cache for a specific provider.
     *
     * @param  array<int, array{id: string, name?: string, context_window?: int}>  $models
     */
    public function updateDiscoveredModels(string $provider, array $models): void
    {
        $settings = $this->get();

        if (isset($settings['providers'][$provider])) {
            $settings['providers'][$provider]['discovered_models'] = $models;
            $settings['providers'][$provider]['last_fetched_at'] = now()->toIso8601String();
            $this->save($settings);
        } elseif (isset($settings['custom_providers'][$provider])) {
            $settings['custom_providers'][$provider]['discovered_models'] = $models;
            $settings['custom_providers'][$provider]['last_fetched_at'] = now()->toIso8601String();
            $this->save($settings);
        }
    }

    /**
     * Persist normalized settings to the general_settings row.
     *
     * @param  array<string, mixed>  $settings
     */
    public function save(array $settings): void
    {
        $generalSetting = GeneralSetting::query()->first();

        if (! $generalSetting instanceof GeneralSetting) {
            $generalSetting = GeneralSetting::query()->create([]);
        }

        $generalSetting->update(['ai_settings' => $settings]);
        $this->clearCache();
    }

    /**
     * Inject stored AI configuration into Laravel's runtime config('ai.*').
     */
    public function applyRuntimeConfig(): void
    {
        try {
            $settings = $this->get();

            if (! ($settings['enabled'] ?? false)) {
                return;
            }

            $primary = (string) ($settings['primary_provider'] ?? 'anthropic');
            $fallback = (string) ($settings['fallback_provider'] ?? '');
            $failoverEnabled = (bool) ($settings['failover_enabled'] ?? false);

            config([
                'ai.default' => $primary,
                'ai.failover' => ($failoverEnabled && filled($fallback) && $fallback !== $primary) ? [$fallback] : [],
            ]);

            foreach ($settings['providers'] as $providerKey => $providerConfig) {
                if (! is_array($providerConfig)) {
                    continue;
                }

                $key = (string) ($providerConfig['api_key'] ?? '');
                $url = (string) ($providerConfig['base_url'] ?? '');
                $chatModel = (string) ($providerConfig['default_chat_model'] ?? '');
                $embeddingsModel = (string) ($providerConfig['default_embeddings_model'] ?? '');

                if (filled($key)) {
                    config(["ai.providers.{$providerKey}.key" => $key]);
                }

                if (filled($url)) {
                    config(["ai.providers.{$providerKey}.url" => $url]);
                }

                if (filled($chatModel)) {
                    config(["ai.providers.{$providerKey}.models.text.default" => $chatModel]);
                }

                if (filled($embeddingsModel)) {
                    config(["ai.providers.{$providerKey}.models.embeddings.default" => $embeddingsModel]);
                }
            }

            // Register dynamic custom OpenAI-compatible providers
            foreach ($settings['custom_providers'] ?? [] as $customKey => $custom) {
                if (! is_array($custom) || blank($custom['base_url'] ?? '')) {
                    continue;
                }

                $modelsConfig = [];
                if (filled($custom['default_chat_model'] ?? null)) {
                    $modelsConfig['text']['default'] = (string) $custom['default_chat_model'];
                }
                if (filled($custom['default_embeddings_model'] ?? null)) {
                    $modelsConfig['embeddings']['default'] = (string) $custom['default_embeddings_model'];
                }

                config([
                    "ai.providers.{$customKey}" => [
                        'driver' => 'openai-compatible',
                        'url' => (string) $custom['base_url'],
                        'key' => (string) ($custom['api_key'] ?? ''),
                        'headers' => is_array($custom['headers'] ?? null) ? $custom['headers'] : [],
                        'models' => $modelsConfig,
                    ],
                ]);
            }
        } catch (Throwable) {
            // Failsafe during pre-database boot or migrations
        }
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Normalize settings against defaults.
     *
     * @param  array<string, mixed>  $stored
     * @return array<string, mixed>
     */
    private function normalize(array $stored): array
    {
        $defaults = $this->defaults();
        $merged = array_merge($defaults, $stored);

        foreach ($defaults['providers'] as $providerKey => $providerDefaults) {
            $merged['providers'][$providerKey] = array_merge(
                $providerDefaults,
                is_array($stored['providers'][$providerKey] ?? null) ? $stored['providers'][$providerKey] : []
            );
        }

        $merged['custom_providers'] = is_array($stored['custom_providers'] ?? null)
            ? $stored['custom_providers']
            : [];

        return $merged;
    }

    private function maskSecret(string $secret): string
    {
        $len = mb_strlen($secret);
        if ($len <= 8) {
            return '••••••••';
        }

        return mb_substr($secret, 0, 3).'••••'.mb_substr($secret, -4);
    }
}
