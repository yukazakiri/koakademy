<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Features\DynamicEnrollmentPolicies;
use App\Models\EnrollmentPolicy;
use App\Models\EnrollmentWorkflowEvent;
use App\Models\StudentEnrollment;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;
use Throwable;

final readonly class EnrollmentPolicyRolloutService
{
    public function __construct(private EnrollmentPolicyCompiler $compiler) {}

    /** @return array<string, mixed> */
    public function report(): array
    {
        $globalPolicy = EnrollmentPolicy::query()
            ->where('scope_key', EnrollmentPolicy::scopeKey([]))
            ->with('activeVersion')
            ->first();

        $errors = [];
        $checksum = null;
        if (! $globalPolicy?->activeVersion) {
            $errors[] = 'Publish a global enrollment policy first.';
        } else {
            try {
                $compiled = $this->compiler->compile([[
                    'version_id' => $globalPolicy->activeVersion->id,
                    'configuration' => $globalPolicy->activeVersion->configuration,
                ]]);
                $checksum = $compiled->checksum;
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $active = Feature::active(DynamicEnrollmentPolicies::class);

        return [
            'state' => $active ? 'active' : ($errors === [] ? 'ready' : 'legacy'),
            'active' => $active,
            'ready' => $errors === [],
            'errors' => $errors,
            'global_policy_id' => $globalPolicy?->id,
            'global_version_id' => $globalPolicy?->active_version_id,
            'checksum' => $checksum,
            'legacy_enrollments' => StudentEnrollment::query()->where('workflow_runtime', StudentEnrollment::WorkflowRuntimeLegacy)->count(),
            'policy_enrollments' => StudentEnrollment::query()->where('workflow_runtime', StudentEnrollment::WorkflowRuntimePolicyV1)->count(),
            'migration_warnings' => EnrollmentWorkflowEvent::query()->where('event_type', 'migration_warning')->count(),
        ];
    }

    public function activate(): void
    {
        $report = $this->report();
        if (! $report['ready']) {
            throw ValidationException::withMessages(['rollout' => $report['errors']]);
        }

        Feature::activateForEveryone(DynamicEnrollmentPolicies::class);
        Feature::for(null)->activate(DynamicEnrollmentPolicies::class);
    }

    public function deactivate(): void
    {
        Feature::deactivateForEveryone(DynamicEnrollmentPolicies::class);
        Feature::for(null)->deactivate(DynamicEnrollmentPolicies::class);
    }
}
