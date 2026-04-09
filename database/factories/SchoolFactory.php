<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
final class SchoolFactory extends Factory
{
    protected $model = School::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uniqueId = $this->faker->unique()->numberBetween(1000, 9999);

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

        $selectedIndex = $this->faker->numberBetween(0, count($schoolNames) - 1);
        $name = $schoolNames[$selectedIndex].' '.$uniqueId;
        $code = $schoolCodes[$selectedIndex].$uniqueId;

        return [
            'name' => $name,
            'code' => $code,
            'description' => $this->faker->paragraph(3),
            'dean_name' => $this->faker->name(),
            'dean_email' => $this->faker->safeEmail(),
            'location' => $this->faker->randomElement([
                'Main Campus Building A',
                'Main Campus Building B',
                'Academic Building 1',
                'Academic Building 2',
                'Professional Studies Building',
                'Science and Technology Building',
            ]),
            'phone' => $this->faker->phoneNumber(),
            'email' => mb_strtolower($code).'@university.edu',
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'metadata' => [
                'established_year' => $this->faker->numberBetween(1980, 2020),
                'accreditation_status' => $this->faker->randomElement(['Accredited', 'Candidate', 'Pending']),
                'student_capacity' => $this->faker->numberBetween(500, 3000),
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
