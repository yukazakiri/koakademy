<?php

declare(strict_types=1);

use App\Features\Onboarding\FeatureClassRegistry;
use Illuminate\Database\Migrations\Migration;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Migrate onboarding feature flags from string-key to class-based Pennant features.
     *
     * This migration:
     * 1. Activates all class-based features that have an active OnboardingFeature record
     * 2. Cleans up old string-key stored values from the features table
     */
    public function up(): void
    {
        // Activate class-based features for all active OnboardingFeature records
        $activeFeatures = App\Models\OnboardingFeature::query()
            ->where('is_active', true)
            ->pluck('feature_key')
            ->all();

        foreach ($activeFeatures as $featureKey) {
            $featureClass = FeatureClassRegistry::classForKey($featureKey);

            if ($featureClass) {
                Feature::activateForEveryone($featureClass);
            }
        }

        // Clean up old string-key stored values from the features table
        // Pennant stores class-based features using the FQCN as the key
        $oldKeys = FeatureClassRegistry::allKeys();

        if ($oldKeys !== []) {
            DB::table('features')
                ->whereIn('name', $oldKeys)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-activate string-key features for active OnboardingFeature records
        $activeFeatures = App\Models\OnboardingFeature::query()
            ->where('is_active', true)
            ->pluck('feature_key')
            ->all();

        foreach ($activeFeatures as $featureKey) {
            Feature::activateForEveryone($featureKey);
        }

        // Remove class-based stored values
        $classKeys = FeatureClassRegistry::allClasses();

        if ($classKeys !== []) {
            DB::table('features')
                ->whereIn('name', $classKeys)
                ->delete();
        }
    }
};
