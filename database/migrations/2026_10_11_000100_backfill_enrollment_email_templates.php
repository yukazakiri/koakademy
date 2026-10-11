<?php

declare(strict_types=1);

use App\Enrollment\EnrollmentPolicyManager;
use App\Models\EnrollmentPolicy;
use App\Services\EnrollmentEmailTemplateService;
use Illuminate\Database\Migrations\Migration;

/**
 * Gives every workflow step of every enrollment policy a built-in email template.
 *
 * Before this backfill an administrator had to hand-add an `enrollment.notify` action
 * to each step, and a step saved without one showed as "No email yet". Only missing or
 * empty templates are filled — an explicitly configured template is never overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $service = app(EnrollmentEmailTemplateService::class);
        $manager = app(EnrollmentPolicyManager::class);

        EnrollmentPolicy::query()
            ->with(['versions' => fn ($query) => $query->latest('version'), 'activeVersion'])
            ->orderBy('id')
            ->chunkById(50, function ($policies) use ($service): void {
                foreach ($policies as $policy) {
                    try {
                        $service->backfillDefaultsFor($policy);
                    } catch (Throwable $exception) {
                        // A single malformed policy must not abort the whole backfill.
                        report($exception);
                    }
                }
            });
    }

    public function down(): void
    {
        // Backfills are additive edits to a draft; there is nothing to safely revert
        // without destroying an administrator's customizations.
    }
};
