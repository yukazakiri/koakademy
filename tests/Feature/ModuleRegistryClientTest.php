<?php

declare(strict_types=1);

use App\Modules\Exceptions\ModuleRegistryException;
use App\Modules\RegistryClient;
use Illuminate\Support\Facades\Http;

function moduleRegistryTestUrl(string $case): string
{
    return "https://registry.example.test/catalog-{$case}.json";
}

function unsignedModuleRegistryPayload(): array
{
    return [
        'schema' => 1,
        'modules' => [[
            'name' => 'LibrarySystem',
            'alias' => 'library-system',
            'description' => 'Library operations.',
            'author' => 'KoAkademy contributors',
            'license' => 'AGPL-3.0-or-later',
            'compatibility' => [
                'laravel' => '^13.0',
                'filament' => '^5.0',
            ],
            'providers' => ['Modules\\LibrarySystem\\Providers\\LibrarySystemServiceProvider'],
            'versions' => [[
                'version' => '1.4.2',
                'asset_url' => 'https://github.com/example/library-system/releases/download/v1.4.2/library-system.zip',
                'sha256' => str_repeat('a', 64),
                'released_at' => '2026-08-01',
                'requires' => [
                    'core' => '>=1.20.0',
                    'php' => '>=8.5',
                ],
            ]],
        ]],
    ];
}

beforeEach(function (): void {
    config([
        'modules-marketplace.enabled' => true,
        'modules-marketplace.require_signature' => false,
        'modules-marketplace.public_key' => null,
        'modules-marketplace.cache_ttl' => 60,
    ]);
});

it('fetches and validates a public module catalog', function (): void {
    $url = moduleRegistryTestUrl('valid');
    config(['modules-marketplace.registry_url' => $url]);
    Http::fake([$url => Http::response(unsignedModuleRegistryPayload())]);

    $entry = app(RegistryClient::class)->find('library-system');

    expect($entry)->not->toBeNull()
        ->and($entry->manifest->version)->toBe('1.4.2')
        ->and($entry->latestRelease()->sha256)->toBe(str_repeat('a', 64));

    Http::assertSentCount(1);
});

it('accepts a catalog with a valid Ed25519 signature', function (): void {
    $keyPair = sodium_crypto_sign_keypair();
    $publicKey = sodium_crypto_sign_publickey($keyPair);
    $secretKey = sodium_crypto_sign_secretkey($keyPair);
    $url = moduleRegistryTestUrl('signed');
    $payload = unsignedModuleRegistryPayload();
    $canonicalPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    $payload['signature'] = [
        'algorithm' => 'ed25519',
        'value' => mb_rtrim(strtr(base64_encode(sodium_crypto_sign_detached($canonicalPayload, $secretKey)), '+/', '-_'), '='),
    ];

    config([
        'modules-marketplace.registry_url' => $url,
        'modules-marketplace.require_signature' => true,
        'modules-marketplace.public_key' => mb_rtrim(strtr(base64_encode($publicKey), '+/', '-_'), '='),
    ]);
    Http::fake([$url => Http::response($payload)]);

    expect(app(RegistryClient::class)->all())->toHaveCount(1);
});

it('refuses an unsigned catalog when signature verification is required', function (): void {
    $url = moduleRegistryTestUrl('unsigned');
    config([
        'modules-marketplace.registry_url' => $url,
        'modules-marketplace.require_signature' => true,
    ]);
    Http::fake([$url => Http::response(unsignedModuleRegistryPayload())]);

    expect(fn (): array => app(RegistryClient::class)->all())
        ->toThrow(ModuleRegistryException::class, 'Ed25519 signature');
});

it('refuses registry URLs that are not HTTPS', function (): void {
    config(['modules-marketplace.registry_url' => 'http://registry.example.test/catalog.json']);

    expect(fn (): array => app(RegistryClient::class)->all())
        ->toThrow(ModuleRegistryException::class, 'must use HTTPS');
});

it('can force a fresh catalog fetch without waiting for the cache ttl', function (): void {
    $url = moduleRegistryTestUrl('refresh');
    config(['modules-marketplace.registry_url' => $url]);

    Http::fake([$url => Http::sequence()
        ->push(unsignedModuleRegistryPayload())
        ->push(unsignedModuleRegistryPayload())]);

    $client = app(RegistryClient::class);
    $client->all();
    $client->all(forceRefresh: true);

    Http::assertSentCount(2);
});
