<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nwidart\Modules\Contracts\RepositoryInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleAdminNavigationService
{
    private const CACHE_KEY = 'admin-navigation-routes:v1';

    public function __construct(
        private readonly RepositoryInterface $modules,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoutes(): array
    {
        return $this->cache->remember(
            self::CACHE_KEY,
            now()->addMinutes(5),
            fn (): array => $this->discoverRoutes(),
        );
    }

    public function forgetCache(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function discoverRoutes(): array
    {
        $routes = [];

        foreach ($this->modules->all() as $module) {
            if (! $module->isEnabled()) {
                continue;
            }

            $moduleName = $module->getName();

            $modulePath = $this->getModulePath($moduleName);
            $modulePages = $this->getModuleInertiaPages($modulePath);
            $modulePagesLookup = array_fill_keys($modulePages, true);

            $configPath = $modulePath.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'navigation.php';
            $configuredLinks = [];

            $config = is_file($configPath) ? require $configPath : null;

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
                    if (is_string($inertiaPage) && $inertiaPage !== '' && ! isset($modulePagesLookup[$inertiaPage])) {
                        continue;
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
    private function getModulePath(string $moduleName): string
    {
        $module = $this->modules->find($moduleName);

        return $module?->getPath() ?? base_path("Modules/{$moduleName}");
    }

    /**
     * @return array<int, string>
     */
    private function getModuleInertiaPages(string $modulePath): array
    {
        $pagesDirectory = $modulePath.DIRECTORY_SEPARATOR.'resources'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'js'.DIRECTORY_SEPARATOR.'Pages';

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

            $withoutPrefix = mb_substr($relativePath, mb_strlen($prefix));
            $withoutExtension = preg_replace('/\.tsx$/', '', $withoutPrefix);
            if (! is_string($withoutExtension)) {
                continue;
            }
            if ($withoutExtension === '') {
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
            if ($moduleSlug === '') {
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

        return mb_strtolower((string) $withDashes);
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
