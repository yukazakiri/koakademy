<?php

declare(strict_types=1);

namespace App\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

/**
 * Installed extensions register consistently; availability is checked at use.
 */
abstract class ExtensionServiceProvider extends ServiceProvider
{
    abstract public function moduleName(): string;

    final public function boot(): void
    {
        $path = $this->modulePath();
        $this->loadMigrationsFrom($path.'/database/migrations');
        $this->loadViewsFrom($path.'/resources/views', $this->moduleName());
        if (! $this->app->routesAreCached() && is_file($path.'/routes/web.php')) {
            Route::middleware(['web', 'ensure.module:'.$this->moduleName()])->group($path.'/routes/web.php');
        }
        $this->bootExtension();
    }

    protected function bootExtension(): void {}

    protected function modulePath(): string
    {
        return dirname((string) (new ReflectionClass(static::class))->getFileName(), 3);
    }
}
