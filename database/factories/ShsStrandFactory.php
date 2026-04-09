<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShsStrand>
 */
final class ShsStrandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'strand_name' => fake()->unique()->randomElement(['STEM', 'ABM', 'HUMSS', 'GAS', 'ICT', 'HOME ECONOMICS', 'INDUSTRIAL ARTS', 'AGRI-FISHERY ARTS']),
            'description' => fake()->optional()->sentence(),
            'track_id' => ShsTrackFactory::new(),
        ];
    }
}
