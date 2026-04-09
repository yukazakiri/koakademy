<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EnrollStat;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StudentEnrollment>
 */
final class StudentEnrollmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StudentEnrollment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => \App\Models\Student::factory(),
            'course_id' => \App\Models\Course::factory(),
            'semester' => $this->faker->randomElement([1, 2]),
            'academic_year' => $this->faker->numberBetween(1, 4),
            'status' => $this->faker->randomElement([
                EnrollStat::Pending->value,
                EnrollStat::VerifiedByDeptHead->value,
                EnrollStat::VerifiedByCashier->value,
            ]),
            'school_year' => '2024 - 2025',
            'remarks' => $this->faker->optional(0.3)->sentence(),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Create an active enrollment
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EnrollStat::VerifiedByCashier->value,
            'created_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    /**
     * Create a completed enrollment
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            // Assuming completed students retain their verified status or moved to history
            // For now, keep as verified but old date
            'status' => EnrollStat::VerifiedByCashier->value,
            'created_at' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
        ]);
    }

    /**
     * Create an enrollment for current semester
     */
    public function currentSemester(): static
    {
        return $this->state(fn (array $attributes): array => [
            'semester' => 1,
            'school_year' => '2024 - 2025',
            'status' => EnrollStat::VerifiedByCashier->value,
            'created_at' => $this->faker->dateTimeBetween('-3 months', 'now'),
        ]);
    }
}
