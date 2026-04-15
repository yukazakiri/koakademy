<?php

declare(strict_types=1);

use App\Features\Onboarding\FeatureClassRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Migrate onboarding feature flags from string-key to class-based Pennant features.
     *
     * This migration:
     * 1. Purges stale stored values so features resolve freshly from OnboardingFeature model
     * 2. Cleans up old string-key stored values from the features table
     */
    public function up(): void
    {
        // Purge all stored feature values so they resolve freshly
        Feature::purge();

        // Clean up old string-key stored values from the features table
        $stringKeys = FeatureClassRegistry::allKeys();

        if ($stringKeys !== []) {
            DB::table('features')
                ->whereIn('name', $stringKeys)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $classKeys = FeatureClassRegistry::allClasses();

        foreach ($classKeys as $featureClass) {
            Feature::forget($featureClass);
        }
    }
};
