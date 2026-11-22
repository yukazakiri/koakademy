<?php

declare(strict_types=1);

use App\Support\HostingSecurity;

it('does not default trustedProxies to wildcard when TRUSTED_PROXIES is unset', function (): void {
    $previousEnv = $_ENV['TRUSTED_PROXIES'] ?? null;
    $previousServer = $_SERVER['TRUSTED_PROXIES'] ?? null;
    $previousGetenv = getenv('TRUSTED_PROXIES');

    unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
    putenv('TRUSTED_PROXIES');

    try {
        expect(HostingSecurity::trustedProxies())->toBeNull();
    } finally {
        if ($previousEnv !== null) {
            $_ENV['TRUSTED_PROXIES'] = $previousEnv;
        }
        if ($previousServer !== null) {
            $_SERVER['TRUSTED_PROXIES'] = $previousServer;
        }
        if ($previousGetenv !== false) {
            putenv('TRUSTED_PROXIES='.$previousGetenv);
        }
    }
});

it('configures an explicit non-wildcard CORS policy with X-KoAkademy-School allowed', function (): void {
    expect(config('cors.paths'))->toContain('api/*')
        ->and(config('cors.allowed_origins'))->not->toContain('*')
        ->and(config('cors.allowed_headers'))->toContain('X-KoAkademy-School')
        ->and(config('cors.supports_credentials'))->toBeFalse();
});

it('does not grant Access-Control-Allow-Origin to untrusted origins', function (): void {
    $response = $this->withHeaders([
        'Origin' => 'https://evil.example',
    ])->getJson('/api/v1/public/settings');

    expect($response->headers->get('Access-Control-Allow-Origin'))
        ->not->toBe('https://evil.example')
        ->not->toBe('*');
});

it('does not ship TRUSTED_PROXIES=* in .env.production.example', function (): void {
    $envExample = (string) file_get_contents(base_path('.env.production.example'));

    expect($envExample)
        ->not->toContain('TRUSTED_PROXIES=*')
        ->toContain('TRUSTED_PROXIES=127.0.0.1,::1');
});
