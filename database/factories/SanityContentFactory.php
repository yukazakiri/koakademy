<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SanityContent>
 */
final class SanityContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sanity_id' => 'test_'.$this->faker->uuid(),
            'title' => $this->faker->sentence(),
            'slug' => $this->faker->slug(),
            'excerpt' => $this->faker->paragraph(),
            'post_kind' => 'news',
            'status' => 'draft',
            'content' => [],
        ];
    }
}
