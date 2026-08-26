<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Contracts\RegistryEntry;
use App\Modules\Exceptions\ModuleRegistryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use JsonException;

final class RegistryClient
{
    /**
     * @return list<RegistryEntry>
     */
    public function all(): array
    {
        if (! (bool) config('modules-marketplace.enabled', false)) {
            return [];
        }

        $url = config('modules-marketplace.registry_url');

        if (! is_string($url) || mb_trim($url) === '') {
            return [];
        }

        if (! str_starts_with($url, 'https://')) {
            throw new ModuleRegistryException('The module registry URL must use HTTPS.');
        }

        $cacheKey = 'module-registry:'.hash('sha256', $url);
        $ttl = max(60, (int) config('modules-marketplace.cache_ttl', 3600));

        return Cache::remember($cacheKey, $ttl, function () use ($url): array {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->retry(2, 100)
                ->get($url)
                ->throw();

            $payload = $response->json();

            if (! is_array($payload)) {
                throw new ModuleRegistryException('The module registry response must be a JSON object.');
            }

            $this->verifySignature($payload);

            if (($payload['schema'] ?? null) !== 1 || ! is_array($payload['modules'] ?? null) || ! array_is_list($payload['modules'])) {
                throw new ModuleRegistryException('The module registry response does not match schema version 1.');
            }

            try {
                return array_map(
                    static fn (mixed $entry): RegistryEntry => is_array($entry)
                        ? RegistryEntry::fromArray($entry)
                        : throw new ModuleRegistryException('Every registry module must be a JSON object.'),
                    $payload['modules'],
                );
            } catch (InvalidArgumentException $exception) {
                throw new ModuleRegistryException("The module registry contains an invalid module: {$exception->getMessage()}", previous: $exception);
            }
        });
    }

    public function find(string $identifier): ?RegistryEntry
    {
        foreach ($this->all() as $entry) {
            if (strcasecmp($entry->manifest->name, $identifier) === 0 || strcasecmp($entry->manifest->alias, $identifier) === 0) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifySignature(array $payload): void
    {
        if (! (bool) config('modules-marketplace.require_signature', true)) {
            return;
        }

        $signature = $payload['signature'] ?? null;
        $publicKey = config('modules-marketplace.public_key');

        if ((! is_string($publicKey) || mb_trim($publicKey) === '') && is_string(config('modules-marketplace.public_key_file'))) {
            $publicKey = is_file(config('modules-marketplace.public_key_file'))
                ? file_get_contents(config('modules-marketplace.public_key_file'))
                : null;
        }

        if (! is_array($signature) || ($signature['algorithm'] ?? null) !== 'ed25519' || ! is_string($signature['value'] ?? null)) {
            throw new ModuleRegistryException('The module registry must include an Ed25519 signature.');
        }

        if (! is_string($publicKey) || mb_trim($publicKey) === '') {
            throw new ModuleRegistryException('The module registry public key is not configured.');
        }

        $signatureBytes = $this->decodeBase64Url($signature['value']);
        $publicKeyBytes = $this->decodeBase64Url($publicKey);

        if (
            $signatureBytes === false
            || $publicKeyBytes === false
            || mb_strlen($signatureBytes, '8bit') !== SODIUM_CRYPTO_SIGN_BYTES
            || mb_strlen($publicKeyBytes, '8bit') !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        ) {
            throw new ModuleRegistryException('The module registry signature or public key is invalid.');
        }

        unset($payload['signature']);

        try {
            $canonicalPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ModuleRegistryException('The module registry payload could not be canonicalized.', previous: $exception);
        }

        if (! sodium_crypto_sign_verify_detached($signatureBytes, $canonicalPayload, $publicKeyBytes)) {
            throw new ModuleRegistryException('The module registry signature could not be verified.');
        }
    }

    private function decodeBase64Url(string $value): string|false
    {
        $normalized = strtr($value, '-_', '+/');
        $remainder = mb_strlen($normalized, '8bit') % 4;

        if ($remainder !== 0) {
            $normalized .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode($normalized, true);
    }
}
