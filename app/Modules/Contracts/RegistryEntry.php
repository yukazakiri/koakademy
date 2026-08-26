<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use InvalidArgumentException;

final readonly class RegistryEntry
{
    /**
     * @param  list<ModuleRelease>  $releases
     */
    public function __construct(
        public ModuleManifest $manifest,
        public array $releases,
        public ?string $repository = null,
        public ?string $homepage = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $releaseData = $data['versions'] ?? [$data];

        if (! is_array($releaseData) || ! array_is_list($releaseData) || $releaseData === []) {
            throw new InvalidArgumentException('Registry module versions must be a non-empty list.');
        }

        $releases = array_map(
            static fn (mixed $release): ModuleRelease => is_array($release)
                ? ModuleRelease::fromArray($release)
                : throw new InvalidArgumentException('Registry module releases must be objects.'),
            $releaseData,
        );

        usort(
            $releases,
            static fn (ModuleRelease $first, ModuleRelease $second): int => version_compare($second->version, $first->version),
        );

        $latest = $releases[0];
        $manifestData = $data;
        $manifestData['version'] = $data['version'] ?? $latest->version;
        $manifestData['requires'] = array_replace(
            is_array($data['requires'] ?? null) ? $data['requires'] : [],
            $latest->requires,
        );
        $manifestData['repository'] = $data['repository'] ?? null;
        $manifestData['homepage'] = $data['homepage'] ?? null;
        unset($manifestData['versions'], $manifestData['signature']);

        return new self(
            manifest: ModuleManifest::fromArray($manifestData),
            releases: $releases,
            repository: is_string($data['repository'] ?? null) ? $data['repository'] : null,
            homepage: is_string($data['homepage'] ?? null) ? $data['homepage'] : null,
        );
    }

    public function latestRelease(): ModuleRelease
    {
        return $this->releases[0];
    }
}
