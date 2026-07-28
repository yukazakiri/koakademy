<?php

declare(strict_types=1);

namespace App\Enrollment;

use App\Enrollment\Exceptions\EnrollmentTransitionException;
use App\Models\EnrollmentPolicySnapshot;
use App\Models\EnrollmentRequirement;
use App\Models\StudentEnrollment;
use App\Models\User;

final readonly class EnrollmentRequirementService
{
    /**
     * @param  array<string, array<string, mixed>>  $evidence
     */
    public function materialize(
        StudentEnrollment $enrollment,
        EnrollmentPolicySnapshot $snapshot,
        array $evidence = [],
    ): void {
        $requirements = is_array($snapshot->configuration['requirements'] ?? null)
            ? $snapshot->configuration['requirements']
            : [];

        foreach ($requirements as $requirement) {
            if (! is_array($requirement) || ($requirement['enabled'] ?? true) === false) {
                continue;
            }

            $key = (string) ($requirement['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $submittedEvidence = $evidence[$key] ?? [];
            $enrollment->requirements()->firstOrCreate(
                ['requirement_key' => $key],
                [
                    'enrollment_policy_snapshot_id' => $snapshot->id,
                    'label' => (string) ($requirement['label'] ?? $key),
                    'description' => $requirement['description'] ?? null,
                    'enforcement_step_key' => $requirement['enforcement_step'] ?? $requirement['enforcement_step_key'] ?? null,
                    'is_required' => (bool) ($requirement['required'] ?? true),
                    'status' => EnrollmentRequirement::Pending,
                    'evidence_path' => is_string($submittedEvidence['path'] ?? null) ? $submittedEvidence['path'] : null,
                    'evidence' => $submittedEvidence === [] ? null : $submittedEvidence,
                ],
            );
        }
    }

    public function assertSatisfiedForStep(StudentEnrollment $enrollment, string $targetStepKey): void
    {
        $blocked = $enrollment->requirements()
            ->where('is_required', true)
            ->where('enforcement_step_key', $targetStepKey)
            ->whereNotIn('status', [EnrollmentRequirement::Verified, EnrollmentRequirement::Waived])
            ->orderBy('id')
            ->pluck('label');

        if ($blocked->isNotEmpty()) {
            throw new EnrollmentTransitionException(
                'Required enrollment items must be verified or waived before this transition: '.$blocked->join(', ').'.',
            );
        }
    }

    public function verify(EnrollmentRequirement $requirement, User $actor, ?string $evidencePath = null): EnrollmentRequirement
    {
        $requirement->forceFill([
            'status' => EnrollmentRequirement::Verified,
            'evidence_path' => $evidencePath ?? $requirement->evidence_path,
            'verified_by' => $actor->id,
            'verified_at' => now(),
            'waived_by' => null,
            'waived_at' => null,
            'waiver_reason' => null,
        ])->save();

        return $requirement->refresh();
    }

    public function waive(EnrollmentRequirement $requirement, User $actor, string $reason): EnrollmentRequirement
    {
        $reason = mb_trim($reason);
        if ($reason === '') {
            throw new EnrollmentTransitionException('A reason is required to waive an enrollment requirement.');
        }

        $requirement->forceFill([
            'status' => EnrollmentRequirement::Waived,
            'waived_by' => $actor->id,
            'waived_at' => now(),
            'waiver_reason' => $reason,
            'verified_by' => null,
            'verified_at' => null,
        ])->save();

        return $requirement->refresh();
    }
}
