<?php

declare(strict_types=1);

namespace App\Enterprise;

use App\Modules\ModuleManifestRepository;
use LogicException;

final readonly class CapabilityRegistry
{
    public function __construct(private ModuleManifestRepository $manifests) {}

    /** @return array<string, Capability> */
    public function all(): array
    {
        $capabilities = [];
        foreach ($this->manifests->all() as $manifest) {
            foreach ($manifest->capabilities as $definition) {
                $capability = Capability::fromArray($definition, $manifest->name);
                if (isset($capabilities[$capability->key])) {
                    throw new LogicException("Capability [{$capability->key}] is declared by more than one installed module.");
                }
                $capabilities[$capability->key] = $capability;
            }
        }

        return $capabilities;
    }

    public function find(string $key): ?Capability
    {
        return $this->all()[$key] ?? null;
    }
}
