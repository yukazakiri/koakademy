<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\ModuleAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureModuleEnabled
{
    public function __construct(private ModuleAccessService $access) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $route = $request->route();
        $owner = $route?->getAction('koakademy_module');
        $module ??= is_string($owner) ? $owner : null;
        $module ??= $route !== null ? $this->access->owningRoute($route)?->manifest->name : null;
        if ($module !== null) {
            abort_unless($this->access->isAvailable($module), 404, 'This extension is unavailable.');
        }

        return $next($request);
    }
}
