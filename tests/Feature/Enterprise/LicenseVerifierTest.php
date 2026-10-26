<?php

declare(strict_types=1);

use App\Enterprise\InvalidLicense;
use App\Enterprise\LicenseVerifier;
use App\Models\School;
use Tests\Support\EnterpriseLicense;

it('accepts a vendor signature and returns the signed grants', function (): void {
    EnterpriseLicense::configure();
    $school = School::factory()->create();
    $claims = EnterpriseLicense::claims($school);

    $license = app(LicenseVerifier::class)->verify(EnterpriseLicense::sign($claims));

    expect($license->grants)->toBe([EnterpriseLicense::CAPABILITY]);
    expect($license->schoolReference)->toBe($claims['school_reference']);
});

it('rejects tampered or untrusted license envelopes', function (string $change): void {
    EnterpriseLicense::configure();
    $claims = EnterpriseLicense::claims(School::factory()->create());
    $envelope = json_decode(EnterpriseLicense::sign($claims), true, flags: JSON_THROW_ON_ERROR);

    match ($change) {
        'payload' => $envelope['payload'] = EnterpriseLicense::encode(json_encode([...$claims, 'grants' => ['enterprise.unpurchased']], JSON_THROW_ON_ERROR)),
        'signature' => $envelope['signature'] = EnterpriseLicense::encode(str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES)),
        'key' => $envelope['key_id'] = 'untrusted',
        'encoding' => $envelope['payload'] .= '=',
    };

    expect(fn () => app(LicenseVerifier::class)->verify(json_encode($envelope, JSON_THROW_ON_ERROR)))->toThrow(InvalidLicense::class);
})->with(['payload', 'signature', 'key', 'encoding']);

it('rejects invalid signed license claims', function (array $overrides): void {
    EnterpriseLicense::configure();
    $claims = EnterpriseLicense::claims(School::factory()->create(), $overrides);

    expect(fn () => app(LicenseVerifier::class)->verify(EnterpriseLicense::sign($claims)))->toThrow(InvalidLicense::class);
})->with([
    'unsupported schema' => [['schema' => 2]],
    'non integer revision' => [['revision' => '1']],
    'invalid installation id' => [['installation_id' => 'other-installation']],
    'missing customer' => [['customer' => '']],
    'wildcard grant' => [['grants' => ['enterprise.*']]],
    'empty grant list' => [['grants' => []]],
    'invalid timestamp' => [['issued_at' => 'yesterday']],
    'impossible date' => [['issued_at' => '2026-02-31T00:00:00+00:00']],
]);

it('rejects oversized and malformed license files', function (string $document): void {
    expect(fn () => app(LicenseVerifier::class)->verify($document))->toThrow(InvalidLicense::class);
})->with(['malformed' => '{', 'oversized' => str_repeat('a', LicenseVerifier::MAX_BYTES + 1)]);

it('rejects a signature created for a different signing context', function (): void {
    EnterpriseLicense::configure();
    $payload = json_encode(EnterpriseLicense::claims(School::factory()->create()), JSON_THROW_ON_ERROR);
    $key = sodium_crypto_sign_secretkey(sodium_crypto_sign_seed_keypair(str_repeat("\x01", SODIUM_CRYPTO_SIGN_SEEDBYTES)));
    $document = json_encode([
        'key_id' => 'default', 'payload' => EnterpriseLicense::encode($payload),
        'signature' => EnterpriseLicense::encode(sodium_crypto_sign_detached($payload, $key)),
    ], JSON_THROW_ON_ERROR);

    expect(fn () => app(LicenseVerifier::class)->verify($document))->toThrow(InvalidLicense::class, 'signature is invalid');
});
