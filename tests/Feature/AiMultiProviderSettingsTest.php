<?php

declare(strict_types=1);

use App\Ai\Agents\StudentAdvisorAgent;
use App\Models\User;
use App\Services\Ai\AiModelFetchService;
use App\Services\Ai\AiSettingsService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->withoutVite();
    app(AiSettingsService::class)->clearCache();
});

it('provides normalized defaults for AI providers and global failover', function (): void {
    $service = app(AiSettingsService::class);
    $settings = $service->get();

    expect($settings['enabled'])->toBeTrue()
        ->and($settings['primary_provider'])->toBe('anthropic')
        ->and($settings['fallback_provider'])->toBe('openai')
        ->and($settings['failover_enabled'])->toBeTrue()
        ->and($settings['providers'])->toHaveKey('anthropic')
        ->and($settings['providers'])->toHaveKey('openai')
        ->and($settings['providers'])->toHaveKey('gemini');
});

it('masks secrets and hides full keys in forAdministration payload', function (): void {
    $service = app(AiSettingsService::class);

    // Save a secret key
    $service->merge([
        'enabled' => true,
        'primary_provider' => 'anthropic',
        'fallback_provider' => 'openai',
        'failover_enabled' => true,
        'request_timeout_seconds' => 45,
        'providers' => [
            'anthropic' => [
                'api_key' => 'sk-ant-api03-super-secret-key-123456789',
                'enabled' => true,
            ],
        ],
    ]);

    $adminPayload = $service->forAdministration();

    expect($adminPayload['providers']['anthropic']['configured'])->toBeTrue()
        ->and($adminPayload['providers']['anthropic']['api_key_masked'])->toContain('••••')
        ->and($adminPayload['providers']['anthropic']['api_key_masked'])->not->toBe('sk-ant-api03-super-secret-key-123456789');
});

it('preserves existing secret API key when updating other fields with blank key', function (): void {
    $service = app(AiSettingsService::class);

    // Initial save with key
    $service->merge([
        'enabled' => true,
        'primary_provider' => 'openai',
        'fallback_provider' => 'anthropic',
        'failover_enabled' => true,
        'request_timeout_seconds' => 60,
        'providers' => [
            'openai' => [
                'api_key' => 'sk-proj-my-original-key-1234567890',
                'default_chat_model' => 'gpt-4o',
            ],
        ],
    ]);

    // Update only the model with blank API key
    $service->merge([
        'enabled' => true,
        'primary_provider' => 'openai',
        'fallback_provider' => 'anthropic',
        'failover_enabled' => true,
        'request_timeout_seconds' => 60,
        'providers' => [
            'openai' => [
                'api_key' => '', // Left blank
                'default_chat_model' => 'gpt-4o-mini',
            ],
        ],
    ]);

    $stored = $service->get();

    expect($stored['providers']['openai']['api_key'])->toBe('sk-proj-my-original-key-1234567890')
        ->and($stored['providers']['openai']['default_chat_model'])->toBe('gpt-4o-mini');
});

it('applies configured runtime configuration to config(ai.*)', function (): void {
    $service = app(AiSettingsService::class);

    $service->merge([
        'enabled' => true,
        'primary_provider' => 'anthropic',
        'fallback_provider' => 'groq',
        'failover_enabled' => true,
        'request_timeout_seconds' => 30,
        'providers' => [
            'anthropic' => [
                'api_key' => 'sk-ant-test-key-runtime',
                'base_url' => 'https://custom-proxy.example.com/v1',
            ],
        ],
    ]);

    $service->applyRuntimeConfig();

    expect(config('ai.default'))->toBe('anthropic')
        ->and(config('ai.failover'))->toBe(['groq'])
        ->and(config('ai.providers.anthropic.key'))->toBe('sk-ant-test-key-runtime')
        ->and(config('ai.providers.anthropic.url'))->toBe('https://custom-proxy.example.com/v1');
});

it('fetches and normalizes models from mocked OpenAI /models endpoint', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/models' => Http::response([
            'object' => 'list',
            'data' => [
                ['id' => 'gpt-4o', 'created' => 1715368132, 'owned_by' => 'system'],
                ['id' => 'gpt-4o-mini', 'created' => 1721272323, 'owned_by' => 'system'],
                ['id' => 'text-embedding-3-small', 'created' => 1705948997, 'owned_by' => 'system'],
            ],
        ], 200),
    ]);

    $fetchService = app(AiModelFetchService::class);
    $result = $fetchService->fetchModels('openai', apiKey: 'sk-test-mock');

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(3)
        ->and($result['models'][0]['id'])->toBe('gpt-4o');
});

it('handles model fetch endpoint errors gracefully without throwing', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.groq.com/openai/v1/models' => Http::response([
            'error' => ['message' => 'Invalid API Key provided.'],
        ], 401),
    ]);

    $fetchService = app(AiModelFetchService::class);
    $result = $fetchService->fetchModels('groq', apiKey: 'invalid-key');

    expect($result['success'])->toBeFalse()
        ->and($result['count'])->toBe(0)
        ->and($result['error'])->toContain('401');
});

it('tests connection and measures latency successfully', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.anthropic.com/v1/models' => Http::response([
            'data' => [
                ['id' => 'claude-3-7-sonnet-20250219', 'display_name' => 'Claude 3.7 Sonnet'],
            ],
        ], 200),
    ]);

    $fetchService = app(AiModelFetchService::class);
    $test = $fetchService->testConnection('anthropic', apiKey: 'sk-ant-test');

    expect($test['success'])->toBeTrue()
        ->and($test['latency_ms'])->toBeGreaterThanOrEqual(0)
        ->and($test['message'])->toContain('Successfully connected');
});

it('supports faking agents in tests with Laravel AI SDK', function (): void {
    StudentAdvisorAgent::fake([
        'Here is the evaluation of your schedule. You have no prerequisite clashes.',
    ]);

    $agent = new StudentAdvisorAgent;
    $response = $agent->prompt('Can I enroll in CS101?');

    expect((string) $response)->toContain('no prerequisite clashes');
    StudentAdvisorAgent::assertPrompted('Can I enroll in CS101?');
});

it('allows super admin to view AI system management page', function (): void {
    $user = User::factory()->create([
        'role' => App\Enums\UserRole::SuperAdmin,
    ]);

    $response = $this->actingAs($user)->get('/administrators/system-management/ai');

    $response->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('administrators/system-management/ai')
            ->has('ai_config')
            ->has('ai_config.providers')
        );
});

it('allows super admin to update AI provider settings via endpoint', function (): void {
    $user = User::factory()->create([
        'role' => App\Enums\UserRole::SuperAdmin,
    ]);

    $response = $this->actingAs($user)->put('/administrators/system-management/ai', [
        'enabled' => true,
        'primary_provider' => 'gemini',
        'fallback_provider' => 'anthropic',
        'failover_enabled' => true,
        'request_timeout_seconds' => 45,
        'providers' => [
            'gemini' => [
                'enabled' => true,
                'api_key' => 'AIzaSyFakeGeminiKey12345',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
                'default_chat_model' => 'gemini-2.0-flash',
                'custom_models' => ['gemini-experimental-preview'],
            ],
        ],
    ]);

    $response->assertRedirect();

    $settings = app(AiSettingsService::class)->get();
    expect($settings['primary_provider'])->toBe('gemini')
        ->and($settings['providers']['gemini']['default_chat_model'])->toBe('gemini-2.0-flash')
        ->and($settings['providers']['gemini']['custom_models'])->toContain('gemini-experimental-preview');
});

it('returns models via AJAX fetch-models endpoint', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://api.openai.com/v1/models' => Http::response([
            'object' => 'list',
            'data' => [
                ['id' => 'gpt-4o', 'created' => 1715368132],
            ],
        ], 200),
    ]);

    $user = User::factory()->create([
        'role' => App\Enums\UserRole::SuperAdmin,
    ]);

    $response = $this->actingAs($user)->postJson('/administrators/system-management/ai/fetch-models', [
        'provider' => 'openai',
        'api_key' => 'sk-test-live-key',
    ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'count' => 1,
            'models' => [
                ['id' => 'gpt-4o'],
            ],
        ]);
});

it('protects ai chat endpoint with authentication', function (): void {
    $response = $this->postJson('/ai/chat', [
        'agent' => 'student_advisor',
        'message' => 'Hello',
    ]);

    $response->assertUnauthorized();
});

it('lists user conversations via ai endpoint', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/ai/conversations');

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

it('supports creating, persisting, and masking custom OpenAI-compatible providers', function (): void {
    $service = app(AiSettingsService::class);

    $service->merge([
        'enabled' => true,
        'primary_provider' => 'campus_vllm',
        'fallback_provider' => 'openai',
        'failover_enabled' => true,
        'request_timeout_seconds' => 60,
        'providers' => [],
        'custom_providers' => [
            'campus_vllm' => [
                'key' => 'campus_vllm',
                'label' => 'Campus GPU Cluster (vLLM)',
                'enabled' => true,
                'api_key' => 'sk-vllm-secret-token-12345',
                'base_url' => 'http://192.168.1.50:8000/v1',
                'headers' => ['X-Node-ID' => 'gpu-01'],
                'default_chat_model' => 'mistralai/Mistral-7B-Instruct-v0.3',
                'default_embeddings_model' => 'BAAI/bge-large-en-v1.5',
                'custom_models' => ['qwen-2.5-coder-32b'],
            ],
        ],
    ]);

    $adminPayload = $service->forAdministration();

    expect($adminPayload['primary_provider'])->toBe('campus_vllm')
        ->and($adminPayload['custom_providers'])->toHaveKey('campus_vllm')
        ->and($adminPayload['custom_providers']['campus_vllm']['label'])->toBe('Campus GPU Cluster (vLLM)')
        ->and($adminPayload['custom_providers']['campus_vllm']['configured'])->toBeTrue()
        ->and($adminPayload['custom_providers']['campus_vllm']['api_key_masked'])->toContain('••••')
        ->and($adminPayload['custom_providers']['campus_vllm']['api_key_masked'])->not->toBe('sk-vllm-secret-token-12345')
        ->and($adminPayload['custom_providers']['campus_vllm']['headers'])->toBe(['X-Node-ID' => 'gpu-01']);
});

it('dynamically registers custom OpenAI-compatible providers in runtime config', function (): void {
    $service = app(AiSettingsService::class);

    $service->merge([
        'enabled' => true,
        'primary_provider' => 'fireworks_ai',
        'fallback_provider' => '',
        'failover_enabled' => false,
        'request_timeout_seconds' => 40,
        'providers' => [],
        'custom_providers' => [
            'fireworks_ai' => [
                'key' => 'fireworks_ai',
                'label' => 'Fireworks AI',
                'enabled' => true,
                'api_key' => 'fw_secret_key_9999',
                'base_url' => 'https://api.fireworks.ai/inference/v1',
                'headers' => ['X-Fireworks-Client' => 'KoAkademy'],
                'default_chat_model' => 'accounts/fireworks/models/llama-v3p3-70b-instruct',
                'default_embeddings_model' => 'nomic-ai/nomic-embed-text-v1.5',
            ],
        ],
    ]);

    $service->applyRuntimeConfig();

    expect(config('ai.default'))->toBe('fireworks_ai')
        ->and(config('ai.providers.fireworks_ai.driver'))->toBe('openai-compatible')
        ->and(config('ai.providers.fireworks_ai.url'))->toBe('https://api.fireworks.ai/inference/v1')
        ->and(config('ai.providers.fireworks_ai.key'))->toBe('fw_secret_key_9999')
        ->and(config('ai.providers.fireworks_ai.headers'))->toBe(['X-Fireworks-Client' => 'KoAkademy'])
        ->and(config('ai.providers.fireworks_ai.models.text.default'))->toBe('accounts/fireworks/models/llama-v3p3-70b-instruct');
});

it('fetches models from custom OpenAI-compatible provider endpoint with custom headers', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'http://gpu-cluster.internal:8000/v1/models' => function (Illuminate\Http\Client\Request $request) {
            expect($request->hasHeader('X-Cluster-Auth'))->toBeTrue()
                ->and($request->header('X-Cluster-Auth')[0])->toBe('node-token-alpha');

            return Http::response([
                'object' => 'list',
                'data' => [
                    ['id' => 'meta-llama/Llama-3.3-70B-Instruct'],
                    ['id' => 'Qwen/Qwen2.5-72B-Instruct'],
                ],
            ], 200);
        },
    ]);

    $fetchService = app(AiModelFetchService::class);
    $result = $fetchService->fetchModels(
        provider: 'custom_cluster',
        apiKey: 'sk-cluster-token',
        baseUrl: 'http://gpu-cluster.internal:8000/v1',
        headers: ['X-Cluster-Auth' => 'node-token-alpha']
    );

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(2)
        ->and($result['models'][0]['id'])->toBe('Qwen/Qwen2.5-72B-Instruct')
        ->and($result['models'][1]['id'])->toBe('meta-llama/Llama-3.3-70B-Instruct');
});
