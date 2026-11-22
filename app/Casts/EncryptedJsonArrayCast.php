<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use JsonException;

/**
 * Encrypts array attributes into a valid JSON envelope so they can be stored
 * safely in PostgreSQL json/jsonb and SQLite columns while remaining readable
 * for pre-migration plaintext JSON rows.
 *
 * @implements CastsAttributes<?array<string, mixed>, ?array<string, mixed>>
 */
final class EncryptedJsonArrayCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value) ? $value : json_decode((string) $value, true);

        if (! is_array($decoded)) {
            return null;
        }

        if (($decoded['__encrypted'] ?? false) === true && is_string($decoded['payload'] ?? null)) {
            try {
                $plaintext = Crypt::decryptString($decoded['payload']);
                $unpacked = json_decode($plaintext, true);

                return is_array($unpacked) ? $unpacked : null;
            } catch (DecryptException) {
                return null;
            }
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws JsonException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR);

        return json_encode([
            '__encrypted' => true,
            'payload' => Crypt::encryptString($json),
        ], JSON_THROW_ON_ERROR);
    }
}
