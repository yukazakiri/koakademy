<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Container\Container;
use Nwidart\Modules\Contracts\ActivatorInterface;
use Nwidart\Modules\Module;

final class DatabaseActivator implements ActivatorInterface
{
    public function __construct(
        private readonly Container $app,
    ) {}

    public function enable(Module $module): void
    {
        $this->setActiveByName($module->getName(), true);
    }

    public function disable(Module $module): void
    {
        $this->setActiveByName($module->getName(), false);
    }

    public function hasStatus(Module|string $module, bool $status): bool
    {
        $moduleName = $module instanceof Module ? $module->getName() : $module;

        return $this->states()->isEnabled($moduleName) === $status;
    }

    public function setActive(Module $module, bool $active): void
    {
        $this->setActiveByName($module->getName(), $active);
    }

    public function setActiveByName(string $name, bool $active): void
    {
        $this->states()->setEnabled($name, $active);
    }

    public function delete(Module $module): void
    {
        $this->states()->setEnabled($module->getName(), false);
    }

    public function reset(): void
    {
        $states = $this->states();

        foreach ($states->enabledModuleNames() as $moduleName) {
            $states->setEnabled($moduleName, false);
        }
    }

    /**
     * Resolve per call so the activator does not pin the first request's scoped repository.
     */
    private function states(): ModuleStateRepository
    {
        return $this->app->make(ModuleStateRepository::class);
    }
}
