<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use InvalidArgumentException;

final readonly class ModuleRelease
{
    /**
     * @param  array<string, string>  $requires
     */
    public function __construct(
        public string $version,
        public string $assetUrl,
        public string $sha256,
        public ?string $releasedAt = null,
        public ?string $signature = null,
        public array $requires = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $version = self::requiredString($data, 'version');
        $assetUrl = self::optionalString($data, 'asset_url')
            ?? self::optionalString($data, 'download')
            ?? self::optionalString($data, 'url');
        $sha256 = self::requiredString($data, 'sha256');

        if ($assetUrl === null || ! str_starts_with($assetUrl, 'https://')) {
            throw new InvalidArgumentException("Module release [{$version}] must use an HTTPS asset URL.");
        }

        if (! preg_match('/^[a-f0-9]{64}$/i', $sha256)) {
            throw new InvalidArgumentException("Module release [{$version}] must declare a SHA-256 checksum.");
        }

        $requires = [];
        if (isset($data['requires'])) {
            if (! is_array($data['requires'])) {
                throw new InvalidArgumentException("Module release [{$version}] requirements must be an object.");
            }

            foreach ($data['requires'] as $key => $value) {
                if (! is_string($key) || ! is_string($value) || mb_trim($key) === '' || mb_trim($value) === '') {
                    throw new InvalidArgumentException("Module release [{$version}] requirements must contain strings.");
                }

                $requires[$key] = mb_trim($value);
            }
        }

        return new self(
            version: $version,
            assetUrl: $assetUrl,
            sha256: mb_strtolower($sha256),
            releasedAt: self::optionalString($data, 'released_at'),
            signature: self::optionalString($data, 'signature'),
            requires: $requires,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException("Module release field [{$key}] must be a non-empty string.");
        }

        return mb_trim($value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function optionalString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value) || mb_trim($value) === '') {
            throw new InvalidArgumentException("Module release field [{$key}] must be a string.");
        }

        return mb_trim($value);
    }
}
