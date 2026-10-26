<?php

declare(strict_types=1);

use App\Enterprise\LicenseClaims;
use App\Enterprise\LicenseVerifier;

require __DIR__.'/../vendor/autoload.php';

// Vendor-side tool only. Never copy a signing key into a school deployment.
$options = getopt('', ['claims:', 'private-key:', 'output:', 'key-id:']);
$secretKey = null;

try {
    foreach (['claims', 'private-key', 'output'] as $option) {
        if (! is_string($options[$option] ?? null) || $options[$option] === '') {
            throw new RuntimeException('Usage: php scripts/issue-enterprise-license.php --claims=claims.json --private-key=/secure/vendor.key --output=school.license [--key-id=default]');
        }
    }

    $claimsFile = $options['claims'];
    $keyFile = $options['private-key'];
    if (! is_file($claimsFile) || ! is_readable($claimsFile) || filesize($claimsFile) > 32768
        || ! is_file($keyFile) || ! is_readable($keyFile) || filesize($keyFile) > 256) {
        throw new RuntimeException('Claims and signing key files must be readable and within their size limits.');
    }

    $payload = file_get_contents($claimsFile);
    $claims = json_decode($payload, true, 16, JSON_THROW_ON_ERROR);
    if (! is_array($claims)) {
        throw new RuntimeException('The claims file must contain a JSON object.');
    }
    LicenseClaims::fromArray($claims);

    $secretKey = base64_decode(mb_trim(file_get_contents($keyFile)), true);
    if ($secretKey === false || mb_strlen($secretKey, '8bit') !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        throw new RuntimeException('The signing key file must contain a base64 Ed25519 secret key.');
    }
    $keyId = $options['key-id'] ?? 'default';
    if (! is_string($keyId) || ! preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $keyId)) {
        throw new RuntimeException('The verification key ID is invalid.');
    }
    $encode = static fn (string $value): string => mb_rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    $document = json_encode([
        'key_id' => $keyId,
        'payload' => $encode($payload),
        'signature' => $encode(sodium_crypto_sign_detached(LicenseVerifier::SIGNING_CONTEXT.$payload, $secretKey)),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;

    $output = @fopen($options['output'], 'x');
    if ($output === false) {
        throw new RuntimeException('The output file must be a new, writable file. Existing files are never overwritten.');
    }
    try {
        if (fwrite($output, $document) !== mb_strlen($document, '8bit')) {
            throw new RuntimeException('The complete license could not be written.');
        }
    } finally {
        fclose($output);
    }
    fwrite(STDOUT, 'License issued successfully.'.PHP_EOL);
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
} finally {
    if (is_string($secretKey)) {
        sodium_memzero($secretKey);
    }
}
