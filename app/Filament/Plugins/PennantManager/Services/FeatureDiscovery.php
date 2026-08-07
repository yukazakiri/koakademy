<?php

declare(strict_types=1);

namespace App\Filament\Plugins\PennantManager\Services;

use Illuminate\Support\Facades\File;
use SplFileInfo;

final class FeatureDiscovery
{
    /**
     * Merge scanned features from source files with any manually listed in config.
     */
    public static function discover(): array
    {
        $manual = config('pennant-manager.discovered_features', []);
        $scanned = self::scanPaths();

        return array_values(array_unique(array_merge($scanned, $manual)));
    }

    private static function scanPaths(): array
    {
        $paths = config('pennant-manager.discovery_paths', [app_path('Providers')]);
        $features = [];

        foreach ($paths as $path) {
            if (! File::exists($path)) {
                continue;
            }

            $files = File::isDirectory($path)
                ? File::allFiles($path)
                : [$path];

            foreach ($files as $file) {
                $content = File::get(
                    $file instanceof SplFileInfo ? $file->getPathname() : $file
                );

                $features = array_merge($features, self::extractFromContent($content));
            }
        }

        return array_unique($features);
    }

    private static function extractFromContent(string $content): array
    {
        $features = [];

        // String-keyed: Feature::define('new-dashboard', ...)
        preg_match_all(
            '/Feature\s*::\s*define\s*\(\s*[\'"]([^\'"]+)[\'"]/m',
            $content,
            $stringMatches
        );

        // Class-based: Feature::define(NewDashboard::class, ...)
        preg_match_all(
            '/Feature\s*::\s*define\s*\(\s*([A-Za-z][A-Za-z0-9_\\\\]+)::class/m',
            $content,
            $classMatches
        );

        $features = array_merge($features, $stringMatches[1] ?? []);

        // Resolve class-based names — Pennant stores the FQCN as the feature name
        foreach ($classMatches[1] ?? [] as $shortClass) {
            // Try to resolve from use statements in the file
            $fqcn = self::resolveClass($shortClass, $content);
            $features[] = $fqcn;
        }

        return $features;
    }

    /**
     * Attempt to resolve a short class name to FQCN via use statements in the file.
     * Falls back to the short name if not found.
     */
    private static function resolveClass(string $shortClass, string $content): string
    {
        preg_match_all(
            '/^use\s+([^\s;]+)\s*;/m',
            $content,
            $useMatches
        );

        foreach ($useMatches[1] ?? [] as $fqcn) {
            if (str_ends_with($fqcn, '\\'.$shortClass)) {
                return $fqcn;
            }
        }

        return $shortClass;
    }
}
