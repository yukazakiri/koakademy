<?php

declare(strict_types=1);

use App\Features\Onboarding\FeatureClassRegistry;
use App\Models\OnboardingFeature;
use Illuminate\Database\Migrations\Migration;
use Laravel\Pennant\Feature;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $features = [
            [
                'feature_key' => 'student-signature-pad',
                'name' => 'Student Signature Pad',
                'audience' => 'all',
                'summary' => 'Allow administrators to capture or upload student signatures directly from the student details page.',
                'badge' => 'New',
                'accent' => 'blue',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Signature Capture',
                        'summary' => 'Draw or upload a signature image directly from the student profile.',
                        'highlights' => ['Draw on canvas', 'Upload an image', 'Drag and drop support'],
                        'stats' => [['label' => 'Route', 'value' => 'administrators.students.show'], ['label' => 'Section', 'value' => 'Student Details']],
                        'badge' => 'Signature',
                        'accent' => 'text-primary',
                        'icon' => 'pen-line',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
            [
                'feature_key' => 'student-avatar-upload',
                'name' => 'Student Avatar Upload',
                'audience' => 'all',
                'summary' => 'Enable drag-and-drop avatar uploads with seamless preview on the student profile.',
                'badge' => 'New',
                'accent' => 'green',
                'cta_label' => null,
                'cta_url' => null,
                'steps' => [
                    [
                        'title' => 'Avatar Upload',
                        'summary' => 'Drag and drop or click to upload a new profile photo with instant preview.',
                        'highlights' => ['Drag and drop', 'Click to browse', 'Optimistic preview'],
                        'stats' => [['label' => 'Route', 'value' => 'administrators.students.show'], ['label' => 'Section', 'value' => 'Student Details']],
                        'badge' => 'Avatar',
                        'accent' => 'text-primary',
                        'icon' => 'camera',
                        'image' => null,
                    ],
                ],
                'is_active' => true,
            ],
        ];

        foreach ($features as $feature) {
            $featureKey = $feature['feature_key'];
            $attributes = $feature;
            unset($attributes['feature_key']);

            OnboardingFeature::query()->firstOrCreate(['feature_key' => $featureKey], $attributes);

            if ($feature['is_active']) {
                $featureClass = FeatureClassRegistry::classForKey($featureKey);
                if ($featureClass) {
                    Feature::purge($featureClass);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $keys = ['student-signature-pad', 'student-avatar-upload'];

        OnboardingFeature::query()->whereIn('feature_key', $keys)->delete();

        foreach ($keys as $key) {
            $featureClass = FeatureClassRegistry::classForKey($key);
            if ($featureClass) {
                Feature::purge($featureClass);
            }
        }
    }
};
