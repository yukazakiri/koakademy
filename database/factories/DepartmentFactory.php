<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Department;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
final class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uniqueId = $this->faker->unique()->numberBetween(100, 999);

        $departmentData = [
            // IT Departments
            ['name' => 'Computer Science', 'code' => 'CS'],
            ['name' => 'Information Systems', 'code' => 'IS'],
            ['name' => 'Information Technology', 'code' => 'IT'],
            ['name' => 'Software Engineering', 'code' => 'SE'],
            ['name' => 'Cybersecurity', 'code' => 'CYBER'],

            // Business Departments
            ['name' => 'Management', 'code' => 'MGT'],
            ['name' => 'Marketing', 'code' => 'MKT'],
            ['name' => 'Finance', 'code' => 'FIN'],
            ['name' => 'Accounting', 'code' => 'ACC'],
            ['name' => 'Human Resources', 'code' => 'HR'],

            // Engineering Departments
            ['name' => 'Civil Engineering', 'code' => 'CE'],
            ['name' => 'Mechanical Engineering', 'code' => 'ME'],
            ['name' => 'Electrical Engineering', 'code' => 'EE'],
            ['name' => 'Chemical Engineering', 'code' => 'CHE'],

            // Arts and Sciences
            ['name' => 'Mathematics', 'code' => 'MATH'],
            ['name' => 'Physics', 'code' => 'PHYS'],
            ['name' => 'Chemistry', 'code' => 'CHEM'],
            ['name' => 'Biology', 'code' => 'BIO'],
            ['name' => 'English', 'code' => 'ENG'],
            ['name' => 'History', 'code' => 'HIST'],

            // Education
            ['name' => 'Elementary Education', 'code' => 'ELEM'],
            ['name' => 'Secondary Education', 'code' => 'SEC'],
            ['name' => 'Special Education', 'code' => 'SPED'],

            // Health Sciences
            ['name' => 'Nursing', 'code' => 'NURS'],
            ['name' => 'Physical Therapy', 'code' => 'PT'],
            ['name' => 'Medical Technology', 'code' => 'MEDTECH'],

            // Hospitality Management
            ['name' => 'Hotel Management', 'code' => 'HM'],
            ['name' => 'Culinary Arts', 'code' => 'CA'],
            ['name' => 'Tourism Management', 'code' => 'TM'],
        ];

        $selected = $this->faker->randomElement($departmentData);

        return [
            'school_id' => School::factory(),
            'name' => $selected['name'].' '.$uniqueId,
            'code' => $selected['code'].$uniqueId,
            'description' => $this->faker->paragraph(2),
            'head_name' => $this->faker->name(),
            'head_email' => $this->faker->safeEmail(),
            'location' => $this->faker->randomElement([
                'Room 101',
                'Room 201',
                'Room 301',
                'Office 1A',
                'Office 2B',
                'Laboratory Building',
                'Faculty Office',
                'Department Office',
            ]),
            'phone' => $this->faker->phoneNumber(),
            'email' => mb_strtolower($selected['code'].$uniqueId).'@university.edu',
            'is_active' => $this->faker->boolean(95), // 95% chance of being active
            'metadata' => [
                'faculty_count' => $this->faker->numberBetween(5, 25),
                'student_count' => $this->faker->numberBetween(100, 800),
                'programs_offered' => $this->faker->numberBetween(2, 6),
                'research_areas' => $this->faker->words(3, true),
            ],
        ];
    }

    /**
     * Indicate that the department is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the department is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Create a department for a specific school.
     */
    public function forSchool(School|int $school): static
    {
        $schoolId = $school instanceof School ? $school->id : $school;

        return $this->state(fn (array $attributes): array => [
            'school_id' => $schoolId,
        ]);
    }

    /**
     * Create a department with specific name and code.
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
