<?php

declare(strict_types=1);

namespace App\Services;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleAdminNavigationService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoutes(): array
    {
        $statusesPath = base_path('modules_statuses.json');

        if (! is_file($statusesPath)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($statusesPath), true);

        if (! is_array($decoded)) {
            return [];
        }

        $routes = [];

        foreach ($decoded as $moduleName => $enabled) {
            if ($enabled !== true) {
                continue;
            }

            $modulePages = $this->getModuleInertiaPages($moduleName);
            $modulePagesLookup = array_fill_keys($modulePages, true);

            $configPath = base_path("Modules/{$moduleName}/config/navigation.php");
            $configuredLinks = [];

            if (! is_file($configPath)) {
                $config = null;
            } else {
                $config = require $configPath;
            }

            if (is_array($config)) {
                foreach (($config['admin'] ?? []) as $route) {
                    if (! is_array($route)) {
                        continue;
                    }

                    $routeLink = $route['link'] ?? null;
                    if (is_string($routeLink) && $routeLink !== '') {
                        $configuredLinks[] = $routeLink;
                    }

                    $inertiaPage = $route['inertiaPage'] ?? null;
                    if (is_string($inertiaPage) && $inertiaPage !== '') {
                        if (! isset($modulePagesLookup[$inertiaPage])) {
                            continue;
                        }
                    }

                    $routes[] = [
                        ...$route,
                        'module' => $moduleName,
                    ];
                }
            }

            foreach ($this->discoverAdminRoutesFromPages($moduleName, $modulePages, $configuredLinks) as $autodiscoveredRoute) {
                $routes[] = [
                    ...$autodiscoveredRoute,
                    'module' => $moduleName,
                ];
            }
        }

        return $routes;
    }

    /**
     * @return array<int, string>
     */
    private function getModuleInertiaPages(string $moduleName): array
    {
        $pagesDirectory = base_path("Modules/{$moduleName}/resources/assets/js/Pages");

        if (! is_dir($pagesDirectory)) {
            return [];
        }

        $pages = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesDirectory));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'tsx') {
                continue;
            }

            $relativePath = str_replace('\\', '/', $file->getPathname());
            $prefix = str_replace('\\', '/', $pagesDirectory).'/';

            if (! str_starts_with($relativePath, $prefix)) {
                continue;
            }

            $withoutPrefix = substr($relativePath, strlen($prefix));
            $withoutExtension = preg_replace('/\.tsx$/', '', $withoutPrefix);

            if (! is_string($withoutExtension) || $withoutExtension === '') {
                continue;
            }

            $pages[] = $withoutExtension;
        }

        return array_values(array_unique($pages));
    }

    /**
     * @param  array<int, string>  $modulePages
     * @param  array<int, string>  $configuredLinks
     * @return array<int, array<string, mixed>>
     */
    private function discoverAdminRoutesFromPages(string $moduleName, array $modulePages, array $configuredLinks): array
    {
        $routes = [];
        $configuredLookup = array_fill_keys($configuredLinks, true);

        foreach ($modulePages as $page) {
            if (! preg_match('#^administrators/([^/]+)/index$#', $page, $matches)) {
                continue;
            }

            $moduleSlug = $matches[1] ?? null;
            if (! is_string($moduleSlug) || $moduleSlug === '') {
                continue;
            }

            $link = '/administrators/'.$moduleSlug;
            if (isset($configuredLookup[$link])) {
                continue;
            }

            $routes[] = [
                'id' => 'module-'.$this->toKebabCase($moduleName).'-'.$moduleSlug,
                'title' => $this->toTitleCase($moduleSlug),
                'link' => $link,
                'section' => $this->resolveSectionFromSlug($moduleSlug),
                'inertiaPage' => $page,
                'autodiscovered' => true,
            ];
        }

        return $routes;
    }

    private function toKebabCase(string $value): string
    {
        $withDashes = preg_replace('/(?<!^)[A-Z]/', '-$0', $value);

        return strtolower((string) $withDashes);
    }

    private function toTitleCase(string $slug): string
    {
        return ucwords(str_replace('-', ' ', $slug));
    }

    private function resolveSectionFromSlug(string $slug): string
    {
        return match ($slug) {
            'library' => 'library',
            'inventory' => 'inventory',
            'finance' => 'finance',
            default => 'core',
        };
    }
}
