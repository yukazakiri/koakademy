<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OnboardingDismissal>
 */
final class OnboardingDismissalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'feature_key' => $this->faker->unique()->slug(2),
            'dismissed_at' => now(),
        ];
    }
}
