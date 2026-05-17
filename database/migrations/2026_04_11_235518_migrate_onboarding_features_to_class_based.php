<?php

declare(strict_types=1);

use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Features\Toggles\OnlineTesdaEnrollment;
use Illuminate\Database\Migrations\Migration;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Migrate onboarding feature flags from string-key to class-based Pennant features.
     *
     * Since the onboarding_features table is being dropped, this migration
     * now only activates the Pennant feature flags (metadata lives in code).
     */
    public function up(): void
    {
        Feature::activateForEveryone(OnlineCollegeEnrollment::class);
        Feature::activateForEveryone(OnlineTesdaEnrollment::class);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Feature::deactivateForEveryone(OnlineCollegeEnrollment::class);
        Feature::deactivateForEveryone(OnlineTesdaEnrollment::class);
    }
};
