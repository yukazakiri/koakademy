<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OnboardingFeature>
 */
final class OnboardingFeatureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'feature_key' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(3, true),
            'audience' => $this->faker->randomElement(['student', 'faculty', 'all']),
            'summary' => $this->faker->sentence(),
            'badge' => $this->faker->words(2, true),
            'accent' => $this->faker->randomElement(['text-primary', 'text-emerald-500', 'text-sky-500']),
            'cta_label' => 'Explore',
            'cta_url' => 'https://example.com',
            'steps' => [
                [
                    'title' => 'Step one',
                    'summary' => $this->faker->sentence(),
                    'highlights' => [$this->faker->words(4, true)],
                    'stats' => [
                        ['label' => 'Ready', 'value' => 'Yes'],
                    ],
                    'badge' => 'Overview',
                    'accent' => 'text-primary',
                    'icon' => 'sparkles',
                    'image' => null,
                ],
            ],
            'is_active' => true,
        ];
    }
}
