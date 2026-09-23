<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SchoolLevel;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<School>
 */
final class SchoolFactory extends Factory
{
    #[Override]
    protected $model = School::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uniqueId = fake()->unique()->numberBetween(1000, 9999);

        $schoolNames = [
            'School of Information Technology',
            'School of Business Administration',
            'School of Engineering',
            'School of Arts and Sciences',
            'School of Education',
            'School of Health Sciences',
            'School of Hospitality Management',
            'School of Criminal Justice',
            'School of Agriculture',
            'School of Fine Arts',
        ];

        $schoolCodes = [
            'SIT',
            'SBA',
            'SOE',
            'SAS',
            'SOED',
            'SHS',
            'SHM',
            'SCJ',
            'SAG',
            'SFA',
        ];

        $selectedIndex = fake()->numberBetween(0, count($schoolNames) - 1);
        $name = $schoolNames[$selectedIndex].' '.$uniqueId;
        $code = $schoolCodes[$selectedIndex].$uniqueId;

        return [
            'name' => $name,
            'code' => $code,
            'school_level' => SchoolLevel::HigherEducation,
            'description' => fake()->paragraph(3),
            'dean_name' => fake()->name(),
            'dean_email' => fake()->safeEmail(),
            'location' => fake()->randomElement([
                'Main Campus Building A',
                'Main Campus Building B',
                'Academic Building 1',
                'Academic Building 2',
                'Professional Studies Building',
                'Science and Technology Building',
            ]),
            'phone' => fake()->phoneNumber(),
            'email' => mb_strtolower($code).'@university.edu',
            'is_active' => true,
            'metadata' => [
                'established_year' => fake()->numberBetween(1980, 2020),
                'accreditation_status' => fake()->randomElement(['Accredited', 'Candidate', 'Pending']),
                'student_capacity' => fake()->numberBetween(500, 3000),
            ],
        ];
    }

    /**
     * Indicate that the school is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the school is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a school with specific name and code.
     */
    public function withNameAndCode(string $name, string $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => $name,
            'code' => mb_strtoupper($code),
            'email' => mb_strtolower($code).'@university.edu',
        ]);
    }
}
