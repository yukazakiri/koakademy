<?php

declare(strict_types=1);

use App\Features\Toggles\StudentAvatarUpload;
use App\Features\Toggles\StudentSignaturePad;
use Illuminate\Database\Migrations\Migration;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Seed student signature pad and avatar upload features.
     * Since the onboarding_features table is being dropped, this migration
     * now only activates the Pennant feature flags (metadata lives in code).
     */
    public function up(): void
    {
        Feature::activateForEveryone(StudentSignaturePad::class);
        Feature::activateForEveryone(StudentAvatarUpload::class);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Feature::deactivateForEveryone(StudentSignaturePad::class);
        Feature::deactivateForEveryone(StudentAvatarUpload::class);
    }
};
