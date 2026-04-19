<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
final class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = ['CCS', 'CBA', 'CTE', 'CHTM'];

        return [
            'code' => $this->faker->unique()->regexify('[A-Z]{2,4}'),
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraph(),
            'department_id' => \App\Models\Department::factory(),
            'units' => $this->faker->numberBetween(120, 180),
            'lec_per_unit' => $this->faker->numberBetween(100, 300),
            'lab_per_unit' => $this->faker->numberBetween(100, 300),
            'year_level' => $this->faker->numberBetween(1, 4),
            'semester' => $this->faker->numberBetween(1, 2),
            'school_year' => $this->faker->randomElement(['2023 - 2024', '2024 - 2025', '2025 - 2026']),
            'curriculum_year' => $this->faker->randomElement(['2018 - 2019', '2024 - 2025']),
            'miscellaneous' => $this->faker->numberBetween(3500, 3700),
            'miscelaneous' => $this->faker->numberBetween(3500, 3700),
            'remarks' => $this->faker->optional()->sentence(),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
        ];
    }
}
