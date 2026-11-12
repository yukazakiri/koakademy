<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class SetApiTenantContext
{
    /**
     * Endpoints that must stay usable before a tenant is resolved.
     *
     * Sign-in, profile, token management and organization switching all
     * operate on the caller's own identity rather than tenant-scoped data,
     * and organization switching is how a caller acquires a tenant.
     */
    private const array TENANT_FREE_PATHS = [
        'api/user',
        'api/auth/*',
        'api/v1/auth/*',
        'api/v1/profile/*',
        'api/organizations',
        'api/organizations/*',
    ];

    public function __construct(private TenantContext $tenantContext) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($request->is(...self::TENANT_FREE_PATHS)) {
            return $next($request);
        }

        $this->tenantContext->requireTenant();

        $requestedSchoolId = $request->header('X-KoAkademy-School');
        $canAccessAll = $this->tenantContext->canAccessAllOrganizations();

        if ($requestedSchoolId !== null) {
            $school = School::query()->find((int) $requestedSchoolId);

            if (! $school instanceof School || (! $canAccessAll && ! $user->hasAccessToOrganization($school))) {
                abort(403, 'No accessible school context is available for this account.');
            }
        } else {
            $school = $user->primaryOrganization();

            if (! $school instanceof School && $canAccessAll) {
                // Platform administrators with no primary school keep their
                // deliberate cross-organization access.
                $this->tenantContext->setApiGlobalAccess();

                return $next($request);
            }

            if (! $school instanceof School || ! $user->hasAccessToOrganization($school)) {
                abort(403, 'No accessible school context is available for this account.');
            }
        }

        $this->tenantContext->setApiSchool($school);
        $request->attributes->set('api_school', $school);

        return $next($request);
    }
}
