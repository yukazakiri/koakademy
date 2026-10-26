<?php

declare(strict_types=1);

namespace App\Enterprise;

use JsonException;
use SodiumException;

final class LicenseVerifier
{
    public const int MAX_BYTES = 65536;

    public const string SIGNING_CONTEXT = "koakademy-enterprise-license:v1\n";

    public function verify(string $document): LicenseClaims
    {
        if (mb_strlen($document, '8bit') > self::MAX_BYTES) {
            throw new InvalidLicense('The license file must not exceed 64 KB.');
        }

        try {
            $envelope = json_decode($document, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($envelope) || ! is_string($envelope['key_id'] ?? null)) {
                throw new InvalidLicense('The license envelope is invalid.');
            }

            $keys = config('enterprise.trusted_keys', []);
            $encodedKey = is_array($keys) ? ($keys[$envelope['key_id']] ?? null) : null;
            if (! is_string($encodedKey) || $encodedKey === '') {
                throw new InvalidLicense('The license was not signed by a trusted vendor key.');
            }

            $key = $this->decode($encodedKey);
            $payload = $this->decode($envelope['payload'] ?? null);
            $signature = $this->decode($envelope['signature'] ?? null);

            if (mb_strlen($key, '8bit') !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || mb_strlen($signature, '8bit') !== SODIUM_CRYPTO_SIGN_BYTES
                || ! sodium_crypto_sign_verify_detached($signature, self::SIGNING_CONTEXT.$payload, $key)) {
                throw new InvalidLicense('The license signature is invalid.');
            }

            $claims = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($claims)) {
                throw new InvalidLicense('The license payload is invalid.');
            }

            return LicenseClaims::fromArray($claims);
        } catch (JsonException|SodiumException) {
            throw new InvalidLicense('The license file is malformed.');
        }
    }

    /** @return non-empty-string */
    private function decode(mixed $encoded): string
    {
        if (! is_string($encoded) || $encoded === '' || ! preg_match('/^[A-Za-z0-9_-]+$/D', $encoded)) {
            throw new InvalidLicense('The license contains invalid base64url data.');
        }

        $decoded = base64_decode(strtr($encoded, '-_', '+/'), true);
        if ($decoded === false || $decoded === '' || mb_rtrim(strtr(base64_encode($decoded), '+/', '-_'), '=') !== $encoded) {
            throw new InvalidLicense('The license contains invalid base64url data.');
        }

        return $decoded;
    }
}
