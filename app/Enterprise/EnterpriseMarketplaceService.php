<?php

declare(strict_types=1);

namespace App\Enterprise;

use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

final readonly class EnterpriseMarketplaceService
{
    public function __construct(private EntitlementService $entitlements, private CapabilityRegistry $registry, private CapabilityAccessService $access) {}

    /** @return array<string, mixed> */
    public function summary(User $user, ?School $school): array
    {
        $keys = config('enterprise.trusted_keys', []);
        $summary = [
            'configured' => is_array($keys) && array_any($keys, fn ($key): bool => is_string($key) && $key !== ''),
            'installation_id' => $this->entitlements->installationId(),
            'school' => null, 'license' => null, 'license_status' => 'not_imported', 'capabilities' => [],
        ];
        if ($school === null || ! $school->is_active || (! $user->is_super_admin && ! $user->hasAccessToOrganization($school))) {
            return $summary;
        }

        $summary['school'] = [
            'id' => $school->id, 'name' => $school->name,
            'reference' => $this->entitlements->schoolReference($school),
        ];
        $license = $this->entitlements->license($school);
        if ($license !== null) {
            $summary['license_status'] = 'valid';
            $summary['license'] = [
                'id' => $license->licenseId, 'revision' => $license->revision, 'customer' => $license->customer,
                'maintenance_until' => $license->maintenanceUntil->format(DATE_ATOM),
                'maintenance_active' => $license->maintenanceUntil >= now(),
                'grants' => $license->grants,
            ];
        } elseif (DB::table('school_entitlements')->where('school_id', $school->id)->whereNotNull('signed_license')->exists()) {
            $summary['license_status'] = 'invalid';
        }

        $states = DB::table('school_capabilities')->where('school_id', $school->id)->pluck('enabled', 'capability_key');
        $summary['capabilities'] = array_values(array_map(function (Capability $capability) use ($school, $user, $license, $states): array {
            return [
                'key' => $capability->key, 'name' => $capability->name, 'description' => $capability->description,
                'module' => $capability->module, 'enabled' => (bool) $states->get($capability->key, false),
                'licensed' => in_array($capability->key, $license->grants ?? [], true),
                'access' => $this->access->decide($capability->key, $user, $school)->toArray(),
                'entry_url' => $capability->entryRoute !== null && Route::has($capability->entryRoute) ? route($capability->entryRoute) : null,
            ];
        }, $this->registry->all()));

        return $summary;
    }
}
