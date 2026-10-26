<?php

declare(strict_types=1);

namespace App\Enterprise;

use InvalidArgumentException;

final readonly class Capability
{
    public function __construct(
        public string $key,
        public string $name,
        public string $description,
        public string $permission,
        public string $module,
        public ?string $feature = null,
        public ?string $entryRoute = null,
    ) {}

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data, string $module): self
    {
        foreach (['key', 'name', 'permission'] as $field) {
            if (! is_string($data[$field] ?? null) || mb_trim($data[$field]) === '') {
                throw new InvalidArgumentException("Capability field [{$field}] must be a non-empty string.");
            }
        }

        if (! preg_match('/^[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*$/D', $data['key']) || mb_strlen($data['key']) > 120) {
            throw new InvalidArgumentException('The capability key is invalid.');
        }
        if (isset($data['description']) && ! is_string($data['description'])) {
            throw new InvalidArgumentException('The capability description must be a string.');
        }
        if (isset($data['feature']) && (! is_string($data['feature']) || $data['feature'] === '')) {
            throw new InvalidArgumentException('The capability feature must be a non-empty string.');
        }

        if (isset($data['entry_route']) && (! is_string($data['entry_route']) || $data['entry_route'] === '')) {
            throw new InvalidArgumentException('The capability entry route must be a non-empty string.');
        }

        return new self($data['key'], $data['name'], $data['description'] ?? '', $data['permission'], $module, $data['feature'] ?? null, $data['entry_route'] ?? null);
    }
}
