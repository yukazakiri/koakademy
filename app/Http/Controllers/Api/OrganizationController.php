<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * OrganizationController
 *
 * Handles organization (school) management for multi-tenancy:
 * - List accessible organizations
 * - Switch current organization context
 * - Create new organizations (for authorized users)
 * - Update organization details
 */
final class OrganizationController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext
    ) {}

    /**
     * Get all organizations the current user can access.
     */
    public function index(): JsonResponse
    {
        $organizations = $this->tenantContext->getAccessibleOrganizations();

        return response()->json([
            'organizations' => $organizations->map(fn (School $org): array => [
                'id' => $org->id,
                'name' => $org->name,
                'code' => $org->code,
                'description' => $org->description,
                'is_active' => $org->is_active,
                'departments_count' => $org->departments()->count(),
                'users_count' => $org->users()->count(),
            ]),
            'current_organization_id' => $this->tenantContext->getCurrentSchoolId(),
            'can_create' => $this->canCreateOrganization(),
        ]);
    }

    /**
     * Get the current organization context.
     */
    public function current(): JsonResponse
    {
        $current = $this->tenantContext->getCurrentSchool();

        if (! $current instanceof School) {
            return response()->json([
                'organization' => null,
                'message' => 'No organization context set',
            ]);
        }

        return response()->json([
            'organization' => [
                'id' => $current->id,
                'name' => $current->name,
                'code' => $current->code,
                'description' => $current->description,
                'dean_name' => $current->dean_name,
                'dean_email' => $current->dean_email,
                'location' => $current->location,
                'phone' => $current->phone,
                'email' => $current->email,
                'is_active' => $current->is_active,
            ],
        ]);
    }

    /**
     * Switch to a different organization.
     */
    public function switch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => ['required', 'integer', 'exists:schools,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organizationId = (int) $request->input('organization_id');

        // Check if user can access this organization
        if (! $this->tenantContext->canAccessOrganization($organizationId)) {
            return response()->json([
                'message' => 'You do not have access to this organization',
            ], 403);
        }

        $organization = School::find($organizationId);

        if (! $organization || ! $organization->is_active) {
            return response()->json([
                'message' => 'Organization not found or inactive',
            ], 404);
        }

        $this->tenantContext->setCurrentSchool($organization);

        return response()->json([
            'message' => 'Switched to organization successfully',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
            ],
        ]);
    }

    /**
     * Create a new organization.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->canCreateOrganization()) {
            return response()->json([
                'message' => 'You do not have permission to create organizations',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'unique:schools,code'],
            'description' => ['nullable', 'string', 'max:1000'],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'dean_email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organization = School::create([
            'name' => $request->input('name'),
            'code' => mb_strtoupper((string) $request->input('code')),
            'description' => $request->input('description'),
            'dean_name' => $request->input('dean_name'),
            'dean_email' => $request->input('dean_email'),
            'location' => $request->input('location'),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'is_active' => true,
        ]);

        // Add the creating user to this organization
        $user = Auth::user();

        if ($user) {
            $user->addToOrganization($organization, [
                'is_primary' => false,
                'role' => 'admin',
            ]);
        }

        return response()->json([
            'message' => 'Organization created successfully',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
            ],
        ], 201);
    }

    /**
     * Update an organization.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $organization = School::find($id);

        if (! $organization) {
            return response()->json([
                'message' => 'Organization not found',
            ], 404);
        }

        if (! $this->canManageOrganization($organization)) {
            return response()->json([
                'message' => 'You do not have permission to manage this organization',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:20', 'unique:schools,code,'.$id],
            'description' => ['nullable', 'string', 'max:1000'],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'dean_email' => ['nullable', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $organization->update($request->only([
            'name',
            'code',
            'description',
            'dean_name',
            'dean_email',
            'location',
            'phone',
            'email',
            'is_active',
        ]));

        return response()->json([
            'message' => 'Organization updated successfully',
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'code' => $organization->code,
                'is_active' => $organization->is_active,
            ],
        ]);
    }

    /**
     * Clear the current organization context.
     */
    public function clear(): JsonResponse
    {
        if (! $this->tenantContext->canAccessAllOrganizations()) {
            return response()->json([
                'message' => 'Only administrators can clear organization context',
            ], 403);
        }

        $this->tenantContext->clear();

        return response()->json([
            'message' => 'Organization context cleared',
        ]);
    }

    /**
     * Check if the current user can create organizations.
     */
    private function canCreateOrganization(): bool
    {
        return $this->tenantContext->canAccessAllOrganizations();
    }

    /**
     * Check if the current user can manage a specific organization.
     */
    private function canManageOrganization(School $organization): bool
    {
        // Super admins can manage all
        if ($this->tenantContext->canAccessAllOrganizations()) {
            return true;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Check if user is a dean or admin of this organization
        if ($user->school_id === $organization->id) {
            return in_array($user->role->value ?? $user->role, [
                'dean',
                'associate_dean',
                'president',
                'vice_president',
            ], true);
        }

        return false;
    }
}
