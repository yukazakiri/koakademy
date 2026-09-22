<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\GeneralSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureMcpEnabled
{
    public function __construct(private readonly GeneralSettingsService $generalSettings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->generalSettings->isMcpEnabled()) {
            abort(404, 'MCP is disabled for this installation.');
        }

        return $next($request);
    }
}
