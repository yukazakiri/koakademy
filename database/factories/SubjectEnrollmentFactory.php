<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubjectEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubjectEnrollment>
 */
final class SubjectEnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SubjectEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => $this->faker->numberBetween(100000, 999999),
            'subject_id' => $this->faker->numberBetween(1, 100),
            'enrollment_id' => $this->faker->numberBetween(1, 1000),
            'semester' => $this->faker->randomElement(['1st Semester', '2nd Semester', 'Summer']),
            'academic_year' => $this->faker->numberBetween(2020, 2024),
            'enrollment_date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'status' => $this->faker->randomElement(['enrolled', 'dropped', 'completed']),
            'units' => $this->faker->numberBetween(1, 5),
            'grade' => $this->faker->optional(0.6)->randomFloat(1, 1.0, 5.0),
            'remarks' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * Create an active subject enrollment
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'enrolled',
            'enrollment_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
            'grade' => null,
        ]);
    }

    /**
     * Create a completed subject enrollment with grade
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'completed',
            'grade' => $this->faker->randomFloat(1, 1.5, 3.0),
        ]);
    }

    /**
     * Create a dropped subject enrollment
     */
    public function dropped(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'dropped',
            'grade' => null,
            'remarks' => 'Student dropped the subject',
        ]);
    }

    /**
     * Create a current semester enrollment
     */
    public function currentSemester(): static
    {
        return $this->state(fn (array $attributes): array => [
            'semester' => '1st Semester',
            'academic_year' => 2024,
            'status' => 'enrolled',
            'enrollment_date' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }
}
