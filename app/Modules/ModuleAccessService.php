<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Contracts\InstalledModule;
use Closure;
use Illuminate\Routing\Route;
use ReflectionClass;
use ReflectionFunction;

final readonly class ModuleAccessService
{
    public function __construct(
        private ModuleManifestRepository $manifests,
        private ModuleStateRepository $states,
        private CompatibilityChecker $compatibility,
    ) {}

    public function isAvailable(string $identifier): bool
    {
        $installed = $this->manifests->installedModule($identifier);
        if ($installed === null || ! $this->states->isEnabled($installed->manifest->name)
            || ! $this->compatibility->check($installed->manifest)->isCompatible()) {
            return false;
        }
        foreach (array_keys($installed->manifest->requires['modules'] ?? []) as $dependency) {
            $required = $this->manifests->installedModule($dependency);
            if ($required === null || ! $this->states->isEnabled($required->manifest->name)) {
                return false;
            }
        }

        return true;
    }

    public function owningClass(string $class): ?InstalledModule
    {
        if (! class_exists($class)) {
            return null;
        }
        $file = (new ReflectionClass($class))->getFileName();

        return is_string($file) ? $this->manifests->owningFile($file) : null;
    }

    public function routeFile(Route $route): ?string
    {
        $action = $route->getAction('uses');
        if ($action instanceof Closure) {
            return (new ReflectionFunction($action))->getFileName() ?: null;
        }
        $controller = $route->getControllerClass();

        return is_string($controller) && class_exists($controller)
            ? ((new ReflectionClass($controller))->getFileName() ?: null) : null;
    }

    public function owningRoute(Route $route): ?InstalledModule
    {
        $name = $route->getAction('koakademy_module');
        if (is_string($name)) {
            return $this->manifests->installedModule($name);
        }
        $file = $this->routeFile($route);

        return $file !== null ? $this->manifests->owningFile($file) : null;
    }
}
