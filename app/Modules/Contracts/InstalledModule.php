<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class InstalledModule
{
    /**
     * @var array<string, list<class-string>>
     */
    private array $classesCache = [];

    /**
     * @var list<array{namespace: string, root: string}>|null
     */
    private ?array $psr4Roots = null;

    public function __construct(
        public readonly ModuleManifest $manifest,
        public readonly string $path,
        public readonly ?string $discoveryPath = null,
    ) {}

    public function containsFile(string $file): bool
    {
        $prefix = $this->path.DIRECTORY_SEPARATOR;

        if (str_starts_with($file, $prefix)) {
            return true;
        }

        return str_starts_with(realpath($file) ?: $file, $prefix);
    }

    public function frontendPath(): ?string
    {
        $root = mb_rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $path = str_replace('\\', '/', $this->discoveryPath ?? $this->path);

        return str_starts_with($path, $root) ? mb_substr($path, mb_strlen($root)) : null;
    }

    /**
     * Resolve class names from the package's PSR-4 map, not its module name.
     *
     * @return list<class-string>
     */
    public function classesIn(string $directory, string $suffix = '.php'): array
    {
        $cacheKey = $directory."\0".$suffix;

        if (array_key_exists($cacheKey, $this->classesCache)) {
            return $this->classesCache[$cacheKey];
        }

        $targetDirectory = realpath($this->path.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory));
        if ($targetDirectory === false || ! is_dir($targetDirectory)) {
            return $this->classesCache[$cacheKey] = [];
        }

        $roots = $this->psr4Roots();
        if ($roots === []) {
            return $this->classesCache[$cacheKey] = [];
        }

        $classes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $targetDirectory, RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), $suffix)) {
                continue;
            }
            $pathname = $file->getPathname();
            foreach ($roots as $entry) {
                if (! str_starts_with($pathname, $entry['root'].DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relativeClass = mb_substr($pathname, mb_strlen($entry['root']) + 1, -4);
                $class = $entry['namespace'].str_replace(['/', '\\'], '\\', $relativeClass);
                if (class_exists($class)) {
                    $classes[] = $class;
                }
            }
        }

        return $this->classesCache[$cacheKey] = array_values(array_unique($classes));
    }

    /**
     * @return list<array{namespace: string, root: string}>
     */
    private function psr4Roots(): array
    {
        if ($this->psr4Roots !== null) {
            return $this->psr4Roots;
        }

        $composerPath = $this->path.DIRECTORY_SEPARATOR.'composer.json';
        if (! is_file($composerPath)) {
            return $this->psr4Roots = [];
        }

        $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $autoload = is_array($composer) ? ($composer['autoload'] ?? []) : [];
        $mappings = is_array($autoload) ? ($autoload['psr-4'] ?? []) : [];

        if (! is_array($mappings)) {
            throw new InvalidArgumentException('Module PSR-4 mappings must be an object.');
        }

        $roots = [];

        foreach ($mappings as $namespace => $paths) {
            if (! is_string($namespace) || (! is_array($paths) && ! is_string($paths))) {
                throw new InvalidArgumentException('Module PSR-4 mappings require namespace strings and directories.');
            }

            foreach ((array) $paths as $relative) {
                if (! is_string($relative)) {
                    throw new InvalidArgumentException('Module autoload directories must be strings.');
                }

                $root = realpath($this->path.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
                if ($root !== false) {
                    $roots[] = ['namespace' => $namespace, 'root' => $root];
                }
            }
        }

        return $this->psr4Roots = $roots;
    }
}
