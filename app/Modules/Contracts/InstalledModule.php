<?php

declare(strict_types=1);

namespace App\Modules\Contracts;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final readonly class InstalledModule
{
    public function __construct(
        public ModuleManifest $manifest,
        public string $path,
        public ?string $discoveryPath = null,
    ) {}

    public function containsFile(string $file): bool
    {
        return str_starts_with(realpath($file) ?: $file, $this->path.DIRECTORY_SEPARATOR);
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
        $composerPath = $this->path.'/composer.json';
        if (! is_file($composerPath) || ! is_dir($this->path.'/'.$directory)) {
            return [];
        }
        $composer = json_decode((string) file_get_contents($composerPath), true, 512, JSON_THROW_ON_ERROR);
        $autoload = is_array($composer) ? ($composer['autoload'] ?? []) : [];
        $mappings = is_array($autoload) ? ($autoload['psr-4'] ?? []) : [];
        if (! is_array($mappings)) {
            throw new InvalidArgumentException('Module PSR-4 mappings must be an object.');
        }
        $classes = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $this->path.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS,
        ));

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), $suffix)) {
                continue;
            }
            foreach ($mappings as $namespace => $paths) {
                if (! is_string($namespace) || (! is_array($paths) && ! is_string($paths))) {
                    throw new InvalidArgumentException('Module PSR-4 mappings require namespace strings and directories.');
                }
                foreach ((array) $paths as $relative) {
                    if (! is_string($relative)) {
                        throw new InvalidArgumentException('Module autoload directories must be strings.');
                    }
                    $root = realpath($this->path.'/'.$relative);
                    if ($root === false || ! str_starts_with($file->getPathname(), $root.DIRECTORY_SEPARATOR)) {
                        continue;
                    }
                    $relativeClass = mb_substr($file->getPathname(), mb_strlen($root) + 1, -4);
                    $class = $namespace.str_replace(['/', '\\'], '\\', $relativeClass);
                    if (class_exists($class)) {
                        $classes[] = $class;
                    }
                }
            }
        }

        return array_values(array_unique($classes));
    }
}
