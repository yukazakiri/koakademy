<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubjectEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

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
    #[Override]
    protected $model = SubjectEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => \App\Models\Student::factory(),
            'subject_id' => \App\Models\Subject::factory(),
            'enrollment_id' => \App\Models\StudentEnrollment::factory(),
            'semester' => $this->faker->randomElement([1, 2]),
            'academic_year' => $this->faker->numberBetween(1, 4),
            'school_year' => '2026 - 2027',
            'classification' => 'internal',
            'grade' => $this->faker->optional(0.6)->randomFloat(2, 1.0, 5.0),
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
