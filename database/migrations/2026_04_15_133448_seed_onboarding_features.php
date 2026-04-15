<?php

declare(strict_types=1);

use App\Models\OnboardingFeature;
use Database\Seeders\OnboardingFeatureSeeder;
use Illuminate\Database\Migrations\Migration;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Seed all onboarding feature toggles so a fresh php artisan migrate
     * produces a fully functional system without requiring db:seed.
     */
    public function up(): void
    {
        foreach (OnboardingFeatureSeeder::featureData() as $feature) {
            $featureKey = $feature['feature_key'];
            $attributes = $feature;
            unset($attributes['feature_key']);

            OnboardingFeature::query()->updateOrCreate(['feature_key' => $featureKey], $attributes);
        }

        Feature::purge();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = array_column(OnboardingFeatureSeeder::featureData(), 'feature_key');

        OnboardingFeature::query()->whereIn('feature_key', $keys)->delete();

        Feature::purge();
    }
};
