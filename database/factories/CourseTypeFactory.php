<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CourseType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseType>
 */
final class CourseTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
        ];
    }
}
