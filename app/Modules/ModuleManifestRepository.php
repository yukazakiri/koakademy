<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Contracts\ModuleManifest;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ModuleManifestRepository
{
    public function __construct(
        private readonly ?string $modulesPath = null,
        private readonly ?string $statusesPath = null,
    ) {}

    /**
     * @return list<ModuleManifest>
     */
    public function all(): array
    {
        $path = $this->modulesPath ?? base_path('Modules');

        if (! is_dir($path)) {
            return [];
        }

        $manifests = [];

        foreach (glob(mb_rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $modulePath) {
            $manifestPath = $modulePath.DIRECTORY_SEPARATOR.'module.json';

            if (! is_file($manifestPath)) {
                continue;
            }

            $manifests[] = $this->read($manifestPath);
        }

        usort($manifests, static fn (ModuleManifest $first, ModuleManifest $second): int => strcasecmp($first->name, $second->name));

        return $manifests;
    }

    public function find(string $identifier): ?ModuleManifest
    {
        foreach ($this->all() as $manifest) {
            if (strcasecmp($manifest->name, $identifier) === 0 || strcasecmp($manifest->alias, $identifier) === 0) {
                return $manifest;
            }
        }

        return null;
    }

    /**
     * @return list<ModuleManifest>
     */
    public function enabled(): array
    {
        $statusesPath = $this->statusesPath ?? base_path('modules_statuses.json');

        if (! is_file($statusesPath)) {
            return [];
        }

        $statuses = json_decode((string) file_get_contents($statusesPath), true);

        if (! is_array($statuses)) {
            throw new RuntimeException('The module status file must contain a JSON object.');
        }

        return array_values(array_filter(
            $this->all(),
            static fn (ModuleManifest $manifest): bool => ($statuses[$manifest->name] ?? false) === true,
        ));
    }

    private function read(string $path): ModuleManifest
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read module manifest [{$path}].");
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Module manifest [{$path}] contains invalid JSON.", previous: $exception);
        }

        if (! is_array($data)) {
            throw new InvalidArgumentException("Module manifest [{$path}] must contain a JSON object.");
        }

        try {
            return ModuleManifest::fromArray($data);
        } catch (InvalidArgumentException $exception) {
            throw new InvalidArgumentException("Invalid module manifest [{$path}]: {$exception->getMessage()}", previous: $exception);
        }
    }
}
