<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enterprise\EntitlementService;
use App\Enterprise\InvalidLicense;
use App\Http\Requests\Administrators\ImportEnterpriseLicenseRequest;
use App\Models\School;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

final class AdministratorEnterpriseLicenseController extends Controller
{
    public function store(ImportEnterpriseLicenseRequest $request, School $school, EntitlementService $entitlements, TenantContext $tenants): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        abort_unless($school->is_active && $tenants->canAccessOrganization($school), 404);

        try {
            $entitlements->import($school, $request->file('license')->getContent(), $user->id);
        } catch (InvalidLicense $exception) {
            throw ValidationException::withMessages(['license' => $exception->getMessage()]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'School license imported. Purchased capabilities can now be enabled.']);
    }
}
