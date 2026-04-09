<?php

declare(strict_types=1);

namespace App\Services;

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

            $configPath = base_path("Modules/{$moduleName}/config/navigation.php");

            if (! is_file($configPath)) {
                continue;
            }

            $config = require $configPath;

            if (! is_array($config)) {
                continue;
            }

            foreach (($config['admin'] ?? []) as $route) {
                if (! is_array($route)) {
                    continue;
                }

                $routes[] = [
                    ...$route,
                    'module' => $moduleName,
                ];
            }
        }

        return $routes;
    }
}
