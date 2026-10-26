<?php

declare(strict_types=1);

namespace App\Enterprise;

use DateTimeImmutable;
use Illuminate\Support\Str;

final readonly class LicenseClaims
{
    /** @param list<string> $grants */
    public function __construct(
        public string $licenseId,
        public int $revision,
        public string $installationId,
        public string $schoolReference,
        public string $customer,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $maintenanceUntil,
        public array $grants,
    ) {}

    /** @param array<array-key, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        if (($payload['schema'] ?? null) !== 1) {
            throw new InvalidLicense('This license format is not supported.');
        }

        foreach (['license_id', 'installation_id', 'school_reference'] as $field) {
            if (! is_string($payload[$field] ?? null) || ! Str::isUuid($payload[$field])) {
                throw new InvalidLicense("The license field [{$field}] must be a UUID.");
            }
        }

        if (! is_int($payload['revision'] ?? null) || $payload['revision'] < 1) {
            throw new InvalidLicense('The license revision must be a positive integer.');
        }

        if (! is_string($payload['customer'] ?? null) || mb_trim($payload['customer']) === '' || mb_strlen($payload['customer']) > 255) {
            throw new InvalidLicense('The license must identify its customer.');
        }

        $grants = $payload['grants'] ?? null;
        if (! is_array($grants) || ! array_is_list($grants) || $grants === [] || count($grants) > 100) {
            throw new InvalidLicense('The license must contain a list of capability grants.');
        }

        foreach ($grants as $grant) {
            if (! is_string($grant) || ! preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $grant) || mb_strlen($grant) > 120) {
                throw new InvalidLicense('The license contains an invalid capability grant.');
            }
        }

        $issuedAt = self::date($payload, 'issued_at');
        $maintenanceUntil = self::date($payload, 'maintenance_until');
        if ($maintenanceUntil < $issuedAt) {
            throw new InvalidLicense('The maintenance period must end after the license was issued.');
        }

        return new self(
            $payload['license_id'], $payload['revision'], $payload['installation_id'],
            $payload['school_reference'], mb_trim($payload['customer']),
            $issuedAt, $maintenanceUntil, array_values(array_unique($grants)),
        );
    }

    /** @param array<array-key, mixed> $payload */
    private static function date(array $payload, string $field): DateTimeImmutable
    {
        $value = $payload[$field] ?? null;
        $date = is_string($value) ? DateTimeImmutable::createFromFormat(DATE_ATOM, $value) : false;

        if ($date === false || $date->format(DATE_ATOM) !== $value) {
            throw new InvalidLicense("The license field [{$field}] must be an ISO 8601 timestamp with a timezone.");
        }

        return $date;
    }
}
