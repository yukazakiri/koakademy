<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShsTrack>
 */
final class ShsTrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'track_name' => fake()->unique()->randomElement(['Academic', 'Technical-Vocational-Livelihood', 'Sports', 'Arts and Design']),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
