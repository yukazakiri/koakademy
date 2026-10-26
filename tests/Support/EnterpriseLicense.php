<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enterprise\EntitlementService;
use App\Enterprise\LicenseVerifier;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EnterpriseLicense
{
    public const string CAPABILITY = 'enterprise.consolidated-reports';

    public static function configure(): void
    {
        config(['enterprise.trusted_keys' => ['default' => self::encode(sodium_crypto_sign_publickey(self::keyPair()))]]);
    }

    /** @param array<string, mixed> $overrides */
    public static function claims(School $school, array $overrides = []): array
    {
        $entitlements = app(EntitlementService::class);

        return [
            'schema' => 1, 'license_id' => (string) Str::uuid(), 'revision' => 1,
            'installation_id' => $entitlements->installationId(),
            'school_reference' => $entitlements->schoolReference($school),
            'customer' => $school->name,
            'issued_at' => now()->subDay()->format(DATE_ATOM),
            'maintenance_until' => now()->addYear()->format(DATE_ATOM),
            'grants' => [self::CAPABILITY], ...$overrides,
        ];
    }

    /** @param array<string, mixed> $claims */
    public static function sign(array $claims): string
    {
        $payload = json_encode($claims, JSON_THROW_ON_ERROR);

        return json_encode([
            'key_id' => 'default', 'payload' => self::encode($payload),
            'signature' => self::encode(sodium_crypto_sign_detached(LicenseVerifier::SIGNING_CONTEXT.$payload, sodium_crypto_sign_secretkey(self::keyPair()))),
        ], JSON_THROW_ON_ERROR);
    }

    public static function activate(School $school): void
    {
        self::configure();
        app(EntitlementService::class)->import($school, self::sign(self::claims($school)));
        DB::table('school_capabilities')->upsert([[
            'school_id' => $school->id, 'capability_key' => self::CAPABILITY,
            'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]], ['school_id', 'capability_key'], ['enabled', 'updated_at']);
    }

    public static function encode(string $value): string
    {
        return mb_rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function keyPair(): string
    {
        // Deterministic fixture key. Never used outside the test suite.
        return sodium_crypto_sign_seed_keypair(str_repeat("\x01", SODIUM_CRYPTO_SIGN_SEEDBYTES));
    }
}
