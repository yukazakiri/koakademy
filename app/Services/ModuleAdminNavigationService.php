<?php

declare(strict_types=1);

namespace App\Services;

use App\Modules\ModuleAccessService;
use App\Modules\ModuleManifestRepository;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModuleAdminNavigationService
{
    private const CACHE_KEY = 'admin-navigation-routes:v2';

    public function __construct(
        private readonly ModuleManifestRepository $modules,
        private readonly CacheRepository $cache,
        private readonly ModuleAccessService $access,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRoutes(): array
    {
        /** @var mixed $cached */
        $cached = $this->cache->remember(
            self::CACHE_KEY,
            now()->addMinutes(5),
            fn (): array => $this->discoverRoutes(),
        );

        $routes = is_array($cached) ? $cached : [];

        $available = [];
        foreach ($routes as $route) {
            if (! is_array($route) || ! is_string($route['module'] ?? null) || ! $this->access->isAvailable($route['module'])) {
                continue;
            }
            /** @var array<string, mixed> $route */
            $available[] = $route;
        }

        return $available;
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

        foreach ($this->modules->installed() as $module) {
            $moduleName = $module->manifest->name;
            $modulePath = $module->path;
            $modulePages = $this->getModuleInertiaPages($modulePath);
            $modulePagesLookup = array_fill_keys($modulePages, true);

            $configPath = $modulePath.DIRECTORY_SEPARATOR.'config'.DIRECTORY_SEPARATOR.'navigation.php';
            $configuredLinks = [];

            $config = is_file($configPath) ? require $configPath : null;

            $configuredRoutes = is_array($config) ? ($config['admin'] ?? []) : [];
            if (is_array($configuredRoutes)) {
                foreach ($configuredRoutes as $route) {
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

                    /** @var array<string, mixed> $route */
                    $routes[] = [
                        ...$route,
                        'module' => $moduleName,
                        'modulePath' => $module->frontendPath(),
                    ];
                }
            }

            foreach ($this->discoverAdminRoutesFromPages($moduleName, $modulePages, $configuredLinks) as $autodiscoveredRoute) {
                $routes[] = [
                    ...$autodiscoveredRoute,
                    'module' => $moduleName,
                    'modulePath' => $module->frontendPath(),
                ];
            }
        }

        return $routes;
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

            $moduleSlug = $matches[1];

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
