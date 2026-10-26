<?php

declare(strict_types=1);

namespace App\Providers;

use App\Modules\Livewire\ModuleAvailabilityHook;
use App\Modules\ModuleAccessService;
use App\Modules\ModuleManifestRepository;
use App\Modules\Queue\ModuleCallQueuedHandler;
use Illuminate\Queue\CallQueuedHandler;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

final class ModuleIntegrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ModuleAccessService::class);
        $this->app->bind(CallQueuedHandler::class, ModuleCallQueuedHandler::class);
    }

    public function boot(): void
    {
        // Fail deployment on ambiguous identities before serving requests.
        $installed = $this->app->make(ModuleManifestRepository::class)->installed();
        Livewire::componentHook(ModuleAvailabilityHook::class);
        foreach ($installed as $module) {
            if (! $this->app->make(ModuleAccessService::class)->isAvailable($module->manifest->name)) {
                continue;
            }
            foreach ($module->classesIn('app/Providers/Filament') as $class) {
                if (is_subclass_of($class, \Filament\PanelProvider::class)) {
                    $this->app->register($class);
                }
            }
        }

        $this->app->booted(function () use ($installed): void {
            $access = $this->app->make(ModuleAccessService::class);
            foreach ($this->app->make(Router::class)->getRoutes()->getRoutes() as $route) {
                $file = $access->routeFile($route);
                if ($file === null) {
                    continue;
                }
                foreach ($installed as $module) {
                    if ($module->containsFile($file)) {
                        // Preserve closure ownership when routes are cached.
                        /** @var array<string, mixed> $action */
                        $action = $route->getAction();
                        $route->setAction([...$action, 'koakademy_module' => $module->manifest->name]);
                        break;
                    }
                }
            }
        });
    }
}
