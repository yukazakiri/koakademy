<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ClassEnrollment>
 */
final class ClassEnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ClassEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => $this->faker->numberBetween(100000, 999999),
            'class_id' => $this->faker->numberBetween(1, 100),
            'status' => true,
            'completion_date' => null,
            'remarks' => $this->faker->optional(0.3)->sentence(),
            'prelim_grade' => $this->faker->optional(0.6)->randomFloat(2, 1.0, 5.0),
            'midterm_grade' => $this->faker->optional(0.6)->randomFloat(2, 1.0, 5.0),
            'finals_grade' => $this->faker->optional(0.6)->randomFloat(2, 1.0, 5.0),
            'total_average' => $this->faker->optional(0.6)->randomFloat(2, 1.0, 5.0),
        ];
    }

    /**
     * Create an active class enrollment
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => true,
        ]);
    }

    /**
     * Create a completed class enrollment with grade
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
            'completion_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'total_average' => $this->faker->randomFloat(2, 1.0, 3.0),
        ]);
    }

    /**
     * Create a dropped class enrollment
     */
    public function dropped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => false,
            'remarks' => 'Student dropped the class',
        ]);
    }
}
