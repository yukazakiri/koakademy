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

        $requestedSchoolId = $request->header('X-KoAkademy-School');
        $school = $requestedSchoolId !== null
            ? School::query()->find((int) $requestedSchoolId)
            : $user->primaryOrganization();

        if (! $school instanceof School || ! $user->hasAccessToOrganization($school)) {
            abort(403, 'No accessible school context is available for this account.');
        }

        $this->tenantContext->setApiSchool($school);
        $request->attributes->set('api_school', $school);

        return $next($request);
    }
}
