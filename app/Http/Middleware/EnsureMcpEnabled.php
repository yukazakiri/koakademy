<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Features\Toggles\McpServerAccess;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
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

        $user = $request->user();

        if (! $user instanceof User || ! Feature::for($user)->active(McpServerAccess::class)) {
            abort(404, 'MCP server access is disabled for this account.');
        }

        return $next($request);
    }
}
