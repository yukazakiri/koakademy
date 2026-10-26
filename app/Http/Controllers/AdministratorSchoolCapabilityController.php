<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enterprise\CapabilityRegistry;
use App\Enterprise\EntitlementService;
use App\Http\Requests\Administrators\UpdateSchoolCapabilityRequest;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdministratorSchoolCapabilityController extends Controller
{
    public function update(
        UpdateSchoolCapabilityRequest $request,
        School $school,
        string $capability,
        CapabilityRegistry $registry,
        EntitlementService $entitlements,
        TenantContext $tenants,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless($school->is_active && $tenants->canAccessOrganization($school), 404);
        abort_unless($registry->find($capability) !== null, 404);
        $enabled = $request->boolean('enabled');

        if ($enabled && ! $entitlements->grants($school, $capability)) {
            throw ValidationException::withMessages(['capability' => 'This school needs a license that includes this capability.']);
        }

        DB::table('school_capabilities')->upsert([[
            'school_id' => $school->id, 'capability_key' => $capability, 'enabled' => $enabled,
            'changed_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]], ['school_id', 'capability_key'], ['enabled', 'changed_by', 'updated_at']);

        return back()->with('flash', ['type' => 'success', 'message' => $enabled ? 'Capability enabled for this school.' : 'Capability turned off for this school.']);
    }
}
