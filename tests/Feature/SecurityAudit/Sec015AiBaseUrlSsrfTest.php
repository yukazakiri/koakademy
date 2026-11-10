<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\AiStudentAdvisor;
use App\Models\User;
use App\Services\Ai\AiEndpointGuard;
use App\Services\Ai\AiModelFetchService;
use App\Services\Ai\AiSettingsService;
use Illuminate\Support\Facades\Http;
use Laravel\Pennant\Feature;

/**
 * SEC-015 · Administrator-configurable AI base URLs must not reach private,
 * loopback, link-local, or cloud metadata networks, and upstream error bodies
 * must not be returned to the caller.
 */
beforeEach(function (): void {
    $this->withoutVite();
    app(AiSettingsService::class)->clearCache();
    config(['ai.security.allowed_private_hosts' => []]);
});

it('rejects private, loopback, link-local, and metadata destinations', function (string $url): void {
    expect(app(AiEndpointGuard::class)->isAllowed($url))->toBeFalse();
})->with([
    'AWS metadata' => 'http://169.254.169.254/latest/meta-data/',
    'AWS metadata over HTTPS' => 'https://169.254.169.254/latest/meta-data/',
    'Redis on loopback' => 'https://127.0.0.1:6379/',
    'Consul on RFC1918' => 'https://10.0.0.5:8500/v1/kv',
    'Campus vLLM on RFC1918' => 'https://192.168.1.50:8000/v1',
    'IPv6 loopback' => 'https://[::1]/',
    'IPv6 unique local' => 'https://[fc00::1]/',
    'IPv6 link-local' => 'https://[fe80::1]/',
    'CGNAT' => 'https://100.64.0.10/',
    'GCP metadata hostname' => 'https://metadata.google.internal/computeMetadata/v1/',
    'Localhost hostname' => 'https://localhost/',
]);

it('requires HTTPS for public provider endpoints', function (): void {
    $guard = app(AiEndpointGuard::class);

    expect($guard->isAllowed('https://1.1.1.1/v1'))->toBeTrue()
        ->and($guard->isAllowed('http://1.1.1.1/v1'))->toBeFalse();
});

it('allows operator-allowlisted private hosts and CIDR ranges', function (): void {
    config([
        'ai.security.allowed_private_hosts' => [
            '192.168.1.50',
            'gpu-cluster.internal',
            '*.internal.example',
            '10.20.0.0/16',
        ],
    ]);

    $guard = app(AiEndpointGuard::class);

    expect($guard->isAllowed('http://192.168.1.50:8000/v1'))->toBeTrue()
        ->and($guard->isAllowed('http://gpu-cluster.internal:8000/v1'))->toBeTrue()
        ->and($guard->isAllowed('http://gpu.internal.example:8000/v1'))->toBeTrue()
        ->and($guard->isAllowed('https://10.20.5.5/v1'))->toBeTrue()
        ->and($guard->isAllowed('https://10.21.5.5/v1'))->toBeFalse();
});

it('never allows cloud metadata endpoints even when allowlisted', function (string $url): void {
    config([
        'ai.security.allowed_private_hosts' => [
            '169.254.0.0/16',
            '100.100.100.200',
            'metadata.google.internal',
        ],
    ]);

    expect(app(AiEndpointGuard::class)->isAllowed($url))->toBeFalse();
})->with([
    'https://169.254.169.254/latest/meta-data/',
    'https://100.100.100.200/latest/meta-data/',
    'https://metadata.google.internal/computeMetadata/v1/',
]);

it('normalizes bare provider hosts to HTTPS instead of plaintext HTTP', function (): void {
    $guard = app(AiEndpointGuard::class);

    expect($guard->normalize('api.example.com/v1'))->toBe('https://api.example.com/v1')
        ->and($guard->normalize('https://api.example.com/v1///'))->toBe('https://api.example.com/v1')
        ->and($guard->normalize(' http://api.example.com/v1/ '))->toBe('http://api.example.com/v1');
});

it('does not dispatch model fetches to unsafe provider endpoints', function (string $url): void {
    Http::preventStrayRequests();
    Http::fake();

    $result = app(AiModelFetchService::class)->fetchModels(
        provider: 'custom_probe',
        apiKey: 'probe-key',
        baseUrl: $url,
    );

    expect($result['success'])->toBeFalse();

    Http::assertNothingSent();
})->with([
    'http://169.254.169.254/latest/meta-data/',
    'https://127.0.0.1:6379/',
    'https://10.0.0.5:8500/v1',
    'https://metadata.google.internal/computeMetadata/v1/',
]);

it('keeps the shipped Ollama endpoint reachable without an allowlist entry', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'http://localhost:11434/api/tags' => Http::response([
            'models' => [
                ['name' => 'llama3:latest'],
            ],
        ], 200),
    ]);

    $result = app(AiModelFetchService::class)->fetchModels(provider: 'ollama');

    expect($result['success'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['models'][0]['id'])->toBe('llama3:latest');
});

it('does not extend the shipped Ollama endpoint to a modified host or port', function (string $url): void {
    Http::preventStrayRequests();
    Http::fake();

    $result = app(AiModelFetchService::class)->fetchModels(
        provider: 'ollama',
        baseUrl: $url,
    );

    expect($result['success'])->toBeFalse();

    Http::assertNothingSent();
})->with([
    'http://localhost:11500',
    'http://127.0.0.1:11434',
    'http://ollama.internal:11434',
]);

it('returns a generic error instead of echoing the upstream response body', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://93.184.216.34/v1/models' => Http::response([
            'error' => [
                'message' => 'INTERNAL_TOKEN=super-secret-value',
            ],
        ], 401),
    ]);

    $result = app(AiModelFetchService::class)->fetchModels(
        provider: 'openai',
        apiKey: 'sk-test-key',
        baseUrl: 'https://93.184.216.34/v1',
    );

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('401')
        ->and($result['error'])->not->toContain('super-secret-value')
        ->and($result['error'])->not->toContain('INTERNAL_TOKEN');
});

it('caps the upstream response size while fetching models', function (): void {
    config(['ai.security.max_response_bytes' => 64]);

    Http::preventStrayRequests();
    Http::fake([
        'https://93.184.216.34/v1/models' => Http::response([
            'data' => [
                ['id' => str_repeat('a', 500)],
            ],
        ], 200),
    ]);

    $result = app(AiModelFetchService::class)->fetchModels(
        provider: 'openai',
        apiKey: 'sk-test-key',
        baseUrl: 'https://93.184.216.34/v1',
    );

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('allowed size');
});

it('does not follow redirects from a provider endpoint', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'https://93.184.216.34/v1/models' => Http::response('', 302, [
            'Location' => 'http://169.254.169.254/latest/meta-data/',
        ]),
    ]);

    $result = app(AiModelFetchService::class)->fetchModels(
        provider: 'openai',
        apiKey: 'sk-test-key',
        baseUrl: 'https://93.184.216.34/v1',
    );

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toContain('302');

    Http::assertSentCount(1);
});

it('rejects unsafe base URLs when saving AI settings', function (string $url): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($user)
        ->from('/administrators/system-management/ai')
        ->put('/administrators/system-management/ai', [
            'enabled' => true,
            'primary_provider' => 'campus_probe',
            'fallback_provider' => null,
            'failover_enabled' => false,
            'request_timeout_seconds' => 30,
            'providers' => [
                'anthropic' => ['enabled' => false],
            ],
            'custom_providers' => [
                [
                    'key' => 'campus_probe',
                    'label' => 'Campus Probe',
                    'base_url' => $url,
                ],
            ],
        ]);

    $response->assertSessionHasErrors(['custom_providers.0.base_url']);
})->with([
    'http://169.254.169.254/latest/meta-data/',
    'https://127.0.0.1:6379/',
    'https://192.168.1.50:8000/v1',
    'https://localhost/v1',
    'http://api.example.com/v1',
]);

it('accepts the shipped default endpoints for every built-in provider', function (): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $providers = [];

    foreach (AiSettingsService::supportedProviders() as $key => $meta) {
        $providers[$key] = [
            'enabled' => $key === 'anthropic',
            'base_url' => (string) ($meta['default_url'] ?? ''),
        ];
    }

    $response = $this->actingAs($user)
        ->from('/administrators/system-management/ai')
        ->put('/administrators/system-management/ai', [
            'enabled' => true,
            'primary_provider' => 'anthropic',
            'fallback_provider' => null,
            'failover_enabled' => false,
            'request_timeout_seconds' => 30,
            'providers' => $providers,
            'custom_providers' => [],
        ]);

    $response->assertSessionHasNoErrors();
});

it('does not extend the shipped default exception to modified endpoints', function (string $url): void {
    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($user)
        ->from('/administrators/system-management/ai')
        ->put('/administrators/system-management/ai', [
            'enabled' => true,
            'primary_provider' => 'ollama',
            'fallback_provider' => null,
            'failover_enabled' => false,
            'request_timeout_seconds' => 30,
            'providers' => [
                'ollama' => ['enabled' => true, 'base_url' => $url],
            ],
            'custom_providers' => [],
        ]);

    $response->assertSessionHasErrors(['providers.ollama.base_url']);
})->with([
    'http://localhost:11500',
    'http://127.0.0.1:11434',
    'http://ollama.internal:11434',
]);

it('accepts an operator-allowlisted private provider when saving AI settings', function (): void {
    config(['ai.security.allowed_private_hosts' => ['192.168.1.50']]);

    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($user)
        ->from('/administrators/system-management/ai')
        ->put('/administrators/system-management/ai', [
            'enabled' => true,
            'primary_provider' => 'campus_probe',
            'fallback_provider' => null,
            'failover_enabled' => false,
            'request_timeout_seconds' => 30,
            'providers' => [
                'anthropic' => ['enabled' => false],
            ],
            'custom_providers' => [
                [
                    'key' => 'campus_probe',
                    'label' => 'Campus Probe',
                    'base_url' => 'http://192.168.1.50:8000/v1',
                ],
            ],
        ]);

    $response->assertSessionHasNoErrors();

    $settings = app(AiSettingsService::class)->get();

    expect($settings['custom_providers']['campus_probe']['base_url'])
        ->toBe('http://192.168.1.50:8000/v1');
});

it('rejects unsafe base URLs on the fetch-models endpoint', function (): void {
    Http::preventStrayRequests();
    Http::fake();

    $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $response = $this->actingAs($user)->postJson('/administrators/system-management/ai/fetch-models', [
        'provider' => 'custom_probe',
        'base_url' => 'https://169.254.169.254/latest/meta-data/',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['base_url']);

    Http::assertNothingSent();
});

it('blocks a stored metadata endpoint before an AI chat request is dispatched', function (): void {
    Feature::flushCache();
    Feature::activateForEveryone(AiStudentAdvisor::class);

    Http::preventStrayRequests();
    Http::fake();

    app(AiSettingsService::class)->merge([
        'enabled' => true,
        'primary_provider' => 'campus_probe',
        'fallback_provider' => null,
        'failover_enabled' => false,
        'request_timeout_seconds' => 30,
        'providers' => [
            'anthropic' => ['enabled' => true, 'api_key' => ''],
        ],
        'custom_providers' => [
            'campus_probe' => [
                'key' => 'campus_probe',
                'label' => 'Campus Probe',
                'enabled' => true,
                'base_url' => 'https://169.254.169.254/latest/meta-data/',
                'default_chat_model' => 'probe-model',
            ],
        ],
    ]);

    $student = User::factory()->create(['role' => UserRole::Student]);

    $response = $this->actingAs($student)->post('/ai/chat', [
        'agent' => 'student_advisor',
        'message' => 'Hello advisor',
    ]);

    $response->assertStatus(422);
    Http::assertNothingSent();
});
