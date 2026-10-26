<?php

declare(strict_types=1);

namespace App\Enterprise;

use App\Models\School;
use App\Models\User;
use App\Modules\CompatibilityChecker;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use App\Services\FeatureAccessService;
use Illuminate\Support\Facades\DB;

final readonly class CapabilityAccessService
{
    public function __construct(
        private CapabilityRegistry $registry,
        private ModuleManifestRepository $manifests,
        private ModuleStateRepository $modules,
        private CompatibilityChecker $compatibility,
        private EntitlementService $entitlements,
        private FeatureAccessService $features,
    ) {}

    public function decide(string $key, User $user, School $school): CapabilityDecision
    {
        if (! $school->is_active || (! $user->is_super_admin && ! $user->hasAccessToOrganization($school))) {
            return new CapabilityDecision(false, 'School context not authorized');
        }

        $capability = $this->registry->find($key);
        if ($capability === null) {
            return new CapabilityDecision(false, 'Capability not installed');
        }

        $manifest = $this->manifests->find($capability->module);
        if ($manifest === null || ! $this->compatibility->check($manifest)->isCompatible()) {
            return new CapabilityDecision(false, 'Package is incompatible');
        }

        if (! $this->modules->isEnabled($capability->module)) {
            return new CapabilityDecision(false, 'Package is disabled');
        }

        if (! $this->entitlements->grants($school, $key)) {
            return new CapabilityDecision(false, 'School license does not include this capability');
        }

        if (! DB::table('school_capabilities')->where('school_id', $school->id)->where('capability_key', $key)->value('enabled')) {
            return new CapabilityDecision(false, 'Capability is turned off for this school');
        }

        if ($capability->feature !== null && ! $this->features->decide($capability->feature, $user, $school->id)->active) {
            return new CapabilityDecision(false, 'Capability is not released for this account');
        }

        return $user->can($capability->permission)
            ? new CapabilityDecision(true, 'Available')
            : new CapabilityDecision(false, 'User permission required');
    }
}
