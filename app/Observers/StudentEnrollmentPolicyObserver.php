<?php

declare(strict_types=1);

namespace App\Observers;

use App\Data\Enrollment\EnrollmentContext;
use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Enrollment\EnrollmentPolicyPreset;
use App\Enrollment\EnrollmentPolicyRegistry;
use App\Enrollment\EnrollmentPolicyResolver;
use App\Enrollment\EnrollmentRuleTiming;
use App\Enrollment\EnrollmentSubmissionContext;
use App\Features\DynamicEnrollmentPolicies;
use App\Models\StudentEnrollment;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

final readonly class StudentEnrollmentPolicyObserver
{
    public function __construct(
        private EnrollmentPolicyResolver $resolver,
        private EnrollmentPolicyRegistry $registry,
        private EnrollmentSubmissionContext $submissionContext,
    ) {}

    public function creating(StudentEnrollment $enrollment): void
    {
        if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimeLegacy) {
            return;
        }

        if (! Feature::active(DynamicEnrollmentPolicies::class)) {
            $enrollment->workflow_runtime = StudentEnrollment::WorkflowRuntimeLegacy;

            return;
        }

        $submission = $this->submissionContext->current();
        $channel = $submission instanceof EnrollmentSubmissionData
            ? $submission->channel
            : $this->channel();
        $enrollment->submission_channel = $channel;
        $context = EnrollmentContext::fromEnrollment(
            $enrollment,
            $channel,
            $submission instanceof EnrollmentSubmissionData ? $submission->actor : null,
            $submission instanceof EnrollmentSubmissionData ? $submission->facts : [],
        );
        $snapshot = $this->resolver->snapshot($context);
        $failures = [];
        foreach ($snapshot->configuration['rules'] ?? [] as $rule) {
            if (! EnrollmentRuleTiming::appliesAtEntry((string) $rule['handler'])) {
                continue;
            }

            $result = $this->registry->rule($rule['handler'])->evaluate($context, $rule['configuration'] ?? []);
            if (! $result->passed) {
                $failures[$rule['key']] = $result->message;
            }
        }
        if ($failures !== []) {
            throw ValidationException::withMessages(['enrollment_policy' => array_values($failures)]);
        }

        $entry = collect(data_get($snapshot->configuration, 'workflow.steps', EnrollmentPolicyPreset::standard()['workflow']['steps']))
            ->firstWhere('entry', true);

        $enrollment->workflow_runtime = StudentEnrollment::WorkflowRuntimePolicyV1;
        $enrollment->enrollment_policy_snapshot_id = $snapshot->id;
        $enrollment->current_step_key = $entry['key'] ?? null;
        $enrollment->status = $entry['status'] ?? $enrollment->status;
        $enrollment->deduplication_key ??= hash('sha256', implode('|', [
            (string) $enrollment->student_id,
            (string) $enrollment->school_id,
            (string) $enrollment->school_year,
            (string) $enrollment->semester,
        ]));
    }

    private function channel(): string
    {
        if (request()->routeIs('enrollment.continuing.*')) {
            return 'continuing';
        }

        if (request()->routeIs('enrollment.*')) {
            return 'public';
        }

        if (request()->is('api/*')) {
            return 'api';
        }

        return 'administrator';
    }
}
