<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetTenantContext Middleware
 *
 * This middleware initializes the tenant (school/organization) context
 * for each request. It ensures that:
 * - The tenant context is set from session or user's primary organization
 * - All subsequent queries are automatically scoped to the current tenant
 * - The context is shared with Inertia views
 */
final readonly class SetTenantContext
{
    public function __construct(
        private TenantContext $tenantContext
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Initialize tenant context from session or user
        $this->initializeTenantContext();

        // Share tenant data with Inertia
        $this->shareWithInertia();

        return $next($request);
    }

    /**
     * Initialize the tenant context from session or authenticated user.
     */
    private function initializeTenantContext(): void
    {
        // Get current school will automatically:
        // 1. Try to get from session
        // 2. Fallback to authenticated user's school
        // 3. Persist to session if found
        $this->tenantContext->getCurrentSchool();
    }

    /**
     * Share tenant-related data with Inertia views.
     */
    private function shareWithInertia(): void
    {
        if (! class_exists(\Inertia\Inertia::class)) {
            return;
        }

        \Inertia\Inertia::share([
            'currentOrganization' => $this->getCurrentOrganizationData(...),
            'organizations' => $this->getAccessibleOrganizationsData(...),
            'canSwitchOrganization' => $this->canSwitchOrganization(...),
        ]);
    }

    /**
     * Get current organization data for the frontend.
     *
     * @return array<string, mixed>|null
     */
    private function getCurrentOrganizationData(): ?array
    {
        $school = $this->tenantContext->getCurrentSchool();

        if (! $school instanceof \App\Models\School) {
            return null;
        }

        return [
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'description' => $school->description,
            'is_active' => $school->is_active,
        ];
    }

    /**
     * Get accessible organizations for the frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getAccessibleOrganizationsData(): array
    {
        $organizations = $this->tenantContext->getAccessibleOrganizations();

        return $organizations->map(fn ($org): array => [
            'id' => $org->id,
            'name' => $org->name,
            'code' => $org->code,
            'is_active' => $org->is_active,
        ])->values()->toArray();
    }

    /**
     * Check if the current user can switch between organizations.
     */
    private function canSwitchOrganization(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Super admins can always switch
        if ($this->tenantContext->canAccessAllOrganizations()) {
            return true;
        }

        // Users with multiple organizations can switch
        return $this->tenantContext->getAccessibleOrganizations()->count() > 1;
    }
}
