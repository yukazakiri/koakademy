<?php

declare(strict_types=1);

use App\Features\Toggles\OnlineCollegeEnrollment;
use App\Features\Toggles\OnlineTesdaEnrollment;
use Illuminate\Database\Migrations\Migration;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Add online enrollment feature flags and activate them by default.
     *
     * Since the onboarding_features table is being dropped, this migration
     * now only activates the Pennant feature flags (metadata lives in code).
     */
    public function up(): void
    {
        Feature::activateForEveryone(OnlineTesdaEnrollment::class);
        Feature::activateForEveryone(OnlineCollegeEnrollment::class);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Feature::forget(OnlineTesdaEnrollment::class);
        Feature::forget(OnlineCollegeEnrollment::class);
    }
};
