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
        private readonly ModuleStateRepository $states,
        private readonly ?string $modulesPath = null,
    ) {}

    /**
     * @return list<ModuleManifest>
     */
    public function all(): array
    {
        $manifests = [];
        $manifestPaths = [];

        foreach ($this->manifestDirectories() as $path) {
            $path = mb_rtrim($path, DIRECTORY_SEPARATOR);
            $manifestPattern = str_contains($path, '*')
                ? $path.DIRECTORY_SEPARATOR.'module.json'
                : $path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'module.json';

            foreach (glob($manifestPattern) ?: [] as $manifestPath) {
                $manifestPaths[realpath($manifestPath) ?: $manifestPath] = $manifestPath;
            }
        }

        foreach ($manifestPaths as $manifestPath) {
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
        return array_values(array_filter(
            $this->all(),
            fn (ModuleManifest $manifest): bool => $this->states->isEnabled($manifest->name),
        ));
    }

    /**
     * @return list<string>
     */
    private function manifestDirectories(): array
    {
        if ($this->modulesPath !== null) {
            return is_dir($this->modulesPath) ? [$this->modulesPath] : [];
        }

        $directories = [base_path('Modules')];

        if ((bool) config('modules.scan.enabled', false)) {
            foreach (config('modules.scan.paths', []) as $path) {
                if (is_string($path) && $path !== '') {
                    $directories[] = $path;
                }
            }
        }

        return $directories;
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
