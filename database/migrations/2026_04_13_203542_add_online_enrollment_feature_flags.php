<?php

declare(strict_types=1);

use App\Features\Onboarding\FeatureClassRegistry;
use App\Models\OnboardingFeature;
use Illuminate\Database\Migrations\Migration;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Add online enrollment feature flags and activate TESDA enrollment by default.
     */
    public function up(): void
    {
        $tesda = OnboardingFeature::create([
            'feature_key' => 'online-tesda-enrollment',
            'name' => 'Online TESDA Enrollment',
            'audience' => 'all',
            'summary' => 'Enable or disable online enrollment for TESDA scholarship programs.',
            'badge' => 'TESDA',
            'accent' => 'text-orange-600',
            'cta_label' => 'Enroll Now',
            'cta_url' => '/enrollment',
            'steps' => [
                [
                    'type' => 'step',
                    'data' => [
                        'title' => 'Select Your Program',
                        'summary' => 'Browse available TESDA courses and choose the right one for you.',
                        'badge' => 'Step 1',
                        'accent' => 'text-orange-600',
                        'icon' => 'sparkles',
                        'image' => null,
                        'highlights' => ['Short-term courses', 'Scholarship available', 'Hands-on training'],
                        'stats' => [
                            ['label' => 'Duration', 'value' => '3-6 months'],
                            ['label' => 'Type', 'value' => 'Vocational'],
                        ],
                    ],
                ],
            ],
            'is_active' => true,
        ]);

        OnboardingFeature::create([
            'feature_key' => 'online-college-enrollment',
            'name' => 'Online College Enrollment',
            'audience' => 'all',
            'summary' => 'Enable or disable online enrollment for college degree programs (BSIT, BSHM, BSBA).',
            'badge' => 'College',
            'accent' => 'text-primary',
            'cta_label' => 'Enroll Now',
            'cta_url' => '/enrollment',
            'steps' => [
                [
                    'type' => 'step',
                    'data' => [
                        'title' => 'Choose Your Degree',
                        'summary' => 'Select from our 4-year degree programs and start your academic journey.',
                        'badge' => 'Step 1',
                        'accent' => 'text-primary',
                        'icon' => 'graduation-cap',
                        'image' => null,
                        'highlights' => ['4-year programs', 'BSIT / BSHM / BSBA', 'Full degree'],
                        'stats' => [
                            ['label' => 'Duration', 'value' => '4 years'],
                            ['label' => 'Type', 'value' => 'Degree'],
                        ],
                    ],
                ],
            ],
            'is_active' => false,
        ]);

        // Activate TESDA enrollment in Pennant
        $tesdaClass = FeatureClassRegistry::classForKey($tesda->feature_key);
        if ($tesdaClass) {
            Feature::activateForEveryone($tesdaClass);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = ['online-tesda-enrollment', 'online-college-enrollment'];

        foreach ($keys as $key) {
            $featureClass = FeatureClassRegistry::classForKey($key);
            if ($featureClass) {
                Feature::forget($featureClass);
            }
        }

        OnboardingFeature::query()
            ->whereIn('feature_key', $keys)
            ->delete();
    }
};
