<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
final class SubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subjectCodes = ['IT101', 'IT102', 'MATH101', 'ENG101', 'SCI101', 'PE101', 'HIST101'];
        $subjectTitles = [
            'Introduction to Computing',
            'Programming Fundamentals',
            'College Mathematics',
            'English Communication',
            'General Science',
            'Physical Education',
            'Philippine History',
        ];

        // Use existing course or create new one if none exists
        $course = \App\Models\Course::query()->inRandomOrder()->first();
        $courseId = $course ? $course->id : \App\Models\Course::factory()->create()->id;

        return [
            'code' => $this->faker->randomElement($subjectCodes).$this->faker->unique()->numberBetween(1, 99999),
            'title' => $this->faker->randomElement($subjectTitles),
            'classification' => $this->faker->randomElement(['credited', 'non_credited', 'internal']),
            'units' => $this->faker->numberBetween(1, 5),
            'lecture' => $this->faker->numberBetween(1, 4),
            'laboratory' => $this->faker->numberBetween(0, 3),
            'pre_riquisite' => null, // Will be set manually if needed
            'academic_year' => $this->faker->numberBetween(1, 4),
            'semester' => $this->faker->numberBetween(1, 2),
            'course_id' => $courseId,
            'group' => $this->faker->optional()->randomElement(['A', 'B', 'C']),
            'is_credited' => $this->faker->boolean(80), // 80% chance of being credited
        ];
    }
}
