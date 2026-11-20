<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Contracts\InstalledModule;
use App\Modules\Contracts\ModuleManifest;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class ModuleManifestRepository
{
    private ?string $cachedInstalledKey = null;

    /**
     * @var list<InstalledModule>|null
     */
    private ?array $cachedInstalled = null;

    /**
     * @var array<string, InstalledModule>
     */
    private array $cachedByIdentifier = [];

    /**
     * @var array<string, ?InstalledModule>
     */
    private array $cachedOwningFiles = [];

    public function __construct(
        private readonly ModuleStateRepository $states,
        private readonly ?string $modulesPath = null,
    ) {}

    /**
     * @return list<ModuleManifest>
     */
    public function all(): array
    {
        return array_map(static fn (InstalledModule $module): ModuleManifest => $module->manifest, $this->installed());
    }

    /**
     * @return list<InstalledModule>
     */
    public function installed(): array
    {
        $directories = $this->manifestDirectories();
        $cacheKey = implode("\0", $directories);

        if ($this->cachedInstalledKey === $cacheKey && $this->cachedInstalled !== null) {
            return $this->cachedInstalled;
        }

        $manifests = [];
        $manifestPaths = [];
        $identities = [];
        $byIdentifier = [];

        foreach ($directories as $path) {
            $path = mb_rtrim($path, DIRECTORY_SEPARATOR);
            $manifestPattern = str_contains($path, '*')
                ? $path.DIRECTORY_SEPARATOR.'module.json'
                : $path.DIRECTORY_SEPARATOR.'*'.DIRECTORY_SEPARATOR.'module.json';

            foreach (glob($manifestPattern) ?: [] as $manifestPath) {
                $manifestPaths[realpath($manifestPath) ?: $manifestPath] = $manifestPath;
            }
        }

        foreach ($manifestPaths as $manifestPath) {
            $manifest = $this->read($manifestPath);
            $path = dirname(realpath($manifestPath) ?: $manifestPath);
            $installedModule = new InstalledModule($manifest, $path, dirname($manifestPath));

            foreach (array_filter([$manifest->name, $manifest->alias, $manifest->composerPackage]) as $identifier) {
                $key = mb_strtolower($identifier);
                if (isset($identities[$key]) && $identities[$key] !== $path) {
                    throw new InvalidArgumentException("Duplicate module identity [{$identifier}] in [{$identities[$key]}] and [{$path}]. Remove the duplicate before deployment.");
                }
                $identities[$key] = $path;
                $byIdentifier[$key] = $installedModule;
            }

            $manifests[] = $installedModule;
        }

        usort($manifests, static fn (InstalledModule $first, InstalledModule $second): int => strcasecmp($first->manifest->name, $second->manifest->name));

        $this->cachedInstalledKey = $cacheKey;
        $this->cachedInstalled = $manifests;
        $this->cachedByIdentifier = $byIdentifier;
        $this->cachedOwningFiles = [];

        return $manifests;
    }

    public function installedModule(string $identifier): ?InstalledModule
    {
        $this->installed();

        return $this->cachedByIdentifier[mb_strtolower($identifier)] ?? null;
    }

    public function owningFile(string $file): ?InstalledModule
    {
        $installed = $this->installed();

        if (array_key_exists($file, $this->cachedOwningFiles)) {
            return $this->cachedOwningFiles[$file];
        }

        $realFile = realpath($file) ?: $file;

        foreach ($installed as $module) {
            if ($module->containsFile($realFile)) {
                return $this->cachedOwningFiles[$file] = $module;
            }
        }

        // Laravel may auto-discover a package even when module scanning is off.
        // Recognize its ownership so availability fails closed in that case.
        $directory = dirname($realFile);
        while ($directory !== dirname($directory)) {
            if (is_file($directory.'/module.json')) {
                return $this->cachedOwningFiles[$file] = new InstalledModule($this->read($directory.'/module.json'), $directory);
            }
            $directory = dirname($directory);
        }

        return $this->cachedOwningFiles[$file] = null;
    }

    public function find(string $identifier): ?ModuleManifest
    {
        $installed = $this->installedModule($identifier);

        if ($installed === null) {
            return null;
        }

        return (strcasecmp($installed->manifest->name, $identifier) === 0
            || strcasecmp($installed->manifest->alias, $identifier) === 0)
            ? $installed->manifest
            : null;
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

        $directories = [config()->string('modules.paths.modules', base_path('Modules'))];

        if ((bool) config('modules.scan.enabled', false)) {
            foreach (config()->array('modules.scan.paths', []) as $path) {
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
