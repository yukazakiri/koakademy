<?php

declare(strict_types=1);

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AiModelFetchService
{
    public function __construct(
        private readonly AiSettingsService $settingsService,
    ) {}

    /**
     * Fetch models from a provider endpoint.
     *
     * @param  array<string, string>  $headers
     * @return array{success: bool, models: array<int, array{id: string, name: string, context_window?: int|null}>, count: int, error?: string|null}
     */
    public function fetchModels(string $provider, ?string $apiKey = null, ?string $baseUrl = null, array $headers = []): array
    {
        $stored = $this->settingsService->get();
        $supported = AiSettingsService::supportedProviders();

        $isCustom = false;
        $label = 'Custom Provider';
        $requiresKey = false;
        $providerConfig = [];

        if (isset($supported[$provider])) {
            $providerConfig = $stored['providers'][$provider] ?? [];
            $requiresKey = $supported[$provider]['requires_key'];
            $label = $supported[$provider]['label'];
        } elseif (isset($stored['custom_providers'][$provider])) {
            $isCustom = true;
            $providerConfig = $stored['custom_providers'][$provider];
            $requiresKey = (bool) ($providerConfig['requires_key'] ?? false);
            $label = (string) ($providerConfig['label'] ?? $provider);
            $headers = array_merge(is_array($providerConfig['headers'] ?? null) ? $providerConfig['headers'] : [], $headers);
        } elseif (filled($baseUrl) || str_starts_with($provider, 'custom_')) {
            $isCustom = true;
            $requiresKey = false;
            $label = 'Custom OpenAI-Compatible Provider';
        } else {
            return [
                'success' => false,
                'models' => [],
                'count' => 0,
                'error' => "Provider '{$provider}' is not supported.",
            ];
        }

        $key = filled($apiKey) ? $apiKey : (string) ($providerConfig['api_key'] ?? '');
        $url = filled($baseUrl) ? $baseUrl : (string) ($providerConfig['base_url'] ?? '');

        if ($requiresKey && blank($key)) {
            return [
                'success' => false,
                'models' => [],
                'count' => 0,
                'error' => "API key is required to fetch models for {$label}.",
            ];
        }

        try {
            $models = match (true) {
                $isCustom || $provider === 'openai-compatible' => $this->fetchOpenAiCompatibleModels($key, $url, $headers),
                $provider === 'anthropic' => $this->fetchAnthropicModels($key, $url),
                $provider === 'openai' => $this->fetchOpenAiModels($key, $url),
                $provider === 'gemini' => $this->fetchGeminiModels($key, $url),
                $provider === 'groq' => $this->fetchGroqModels($key, $url),
                $provider === 'deepseek' => $this->fetchDeepSeekModels($key, $url),
                $provider === 'mistral' => $this->fetchMistralModels($key, $url),
                $provider === 'openrouter' => $this->fetchOpenRouterModels($key, $url),
                $provider === 'ollama' => $this->fetchOllamaModels($url),
                default => throw new InvalidArgumentException("No fetcher implemented for provider {$provider}"),
            };

            // Update discovered models cache in settings
            $this->settingsService->updateDiscoveredModels($provider, $models);

            return [
                'success' => true,
                'models' => $models,
                'count' => count($models),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'models' => [],
                'count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connection latency and credential validity.
     *
     * @param  array<string, string>  $headers
     * @return array{success: bool, latency_ms: int, message: string}
     */
    public function testConnection(string $provider, ?string $apiKey = null, ?string $baseUrl = null, array $headers = []): array
    {
        $startTime = microtime(true);
        $result = $this->fetchModels($provider, $apiKey, $baseUrl, $headers);
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        if ($result['success']) {
            return [
                'success' => true,
                'latency_ms' => $latencyMs,
                'message' => "Successfully connected! Discovered {$result['count']} models in {$latencyMs}ms.",
            ];
        }

        return [
            'success' => false,
            'latency_ms' => $latencyMs,
            'message' => 'Connection failed: '.($result['error'] ?? 'Unknown error'),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchOpenAiModels(string $key, ?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'https://api.openai.com/v1', '/').'/models';

        $response = Http::timeout(10)
            ->withToken($key)
            ->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('data') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) $item['id'],
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchAnthropicModels(string $key, ?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'https://api.anthropic.com/v1', '/').'/models';

        $response = Http::timeout(10)
            ->withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])
            ->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('data') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) ($item['display_name'] ?? $item['id']),
            ])
            ->sortByDesc('id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchGeminiModels(string $key, ?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'https://generativelanguage.googleapis.com/v1beta', '/').'/models';

        $response = Http::timeout(10)->get($endpoint, ['key' => $key]);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('models') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['name']))
            ->map(function (array $item): array {
                $rawName = (string) $item['name'];
                $cleanId = str_starts_with($rawName, 'models/') ? mb_substr($rawName, 7) : $rawName;

                return [
                    'id' => $cleanId,
                    'name' => (string) ($item['displayName'] ?? $cleanId),
                ];
            })
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchGroqModels(string $key, ?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'https://api.groq.com/openai/v1', '/').'/models';

        $response = Http::timeout(10)->withToken($key)->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('data') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) $item['id'],
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchDeepSeekModels(string $key, ?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'https://api.deepseek.com', '/').'/models';

        $response = Http::timeout(10)->withToken($key)->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('data') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) $item['id'],
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchMistralModels(string $key, ?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'https://api.mistral.ai/v1', '/').'/models';

        $response = Http::timeout(10)->withToken($key)->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('data') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) $item['id'],
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchOpenRouterModels(string $key, ?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'https://openrouter.ai/api/v1', '/').'/models';

        $response = Http::timeout(10)->withToken($key)->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('data') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) ($item['name'] ?? $item['id']),
                'context_window' => isset($item['context_length']) ? (int) $item['context_length'] : null,
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchOllamaModels(?string $url): array
    {
        $endpoint = mb_rtrim($url ?: 'http://localhost:11434', '/').'/api/tags';

        $response = Http::timeout(10)->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('models') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && (isset($item['name']) || isset($item['model'])))
            ->map(fn (array $item): array => [
                'id' => (string) ($item['name'] ?? $item['model']),
                'name' => (string) ($item['name'] ?? $item['model']),
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<int, array{id: string, name: string, context_window?: int|null}>
     */
    private function fetchOpenAiCompatibleModels(string $key, ?string $url, array $headers = []): array
    {
        if (blank($url)) {
            throw new InvalidArgumentException('Base URL is required for OpenAI-Compatible providers.');
        }

        $trimmed = mb_rtrim($url, '/');
        $endpoint = str_ends_with($trimmed, '/v1') ? $trimmed.'/models' : $trimmed.'/v1/models';

        $request = Http::timeout(10);
        if (filled($key)) {
            $request = $request->withToken($key);
        }

        if (! empty($headers)) {
            $request = $request->withHeaders($headers);
        }

        $response = $request->get($endpoint);

        // Try direct /models if /v1/models fails with 404
        if ($response->status() === 404) {
            $endpoint = $trimmed.'/models';
            $response = $request->get($endpoint);
        }

        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpError($response));
        }

        $data = $response->json('data') ?? [];

        return collect($data)
            ->filter(fn ($item): bool => is_array($item) && isset($item['id']))
            ->map(fn (array $item): array => [
                'id' => (string) $item['id'],
                'name' => (string) ($item['name'] ?? $item['id']),
            ])
            ->sortBy('id')
            ->values()
            ->all();
    }

    private function formatHttpError(\Illuminate\Http\Client\Response $response): string
    {
        $status = $response->status();
        $error = $response->json('error.message') ?? $response->json('message') ?? $response->body();

        if (is_array($error)) {
            $error = json_encode($error);
        }

        return "HTTP {$status}: ".(filled($error) ? (string) $error : 'Request failed.');
    }
}
