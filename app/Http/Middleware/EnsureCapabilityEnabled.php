<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enterprise\CapabilityAccessService;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureCapabilityEnabled
{
    public function __construct(private CapabilityAccessService $access, private TenantContext $tenants) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();
        $school = $this->tenants->getCurrentSchool();
        abort_unless($user instanceof User && $school instanceof School, 403, 'An authenticated school context is required.');

        $decision = $this->access->decide($capability, $user, $school);
        abort_unless($decision->allowed, 403, $decision->reason);

        return $next($request);
    }
}
