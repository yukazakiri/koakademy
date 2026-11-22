<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
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
        $catalog = [
            ['code' => 'IT101', 'title' => 'Introduction to Computing', 'lecture' => 2, 'laboratory' => 1],
            ['code' => 'IT102', 'title' => 'Programming Fundamentals', 'lecture' => 2, 'laboratory' => 1],
            ['code' => 'MATH101', 'title' => 'College Mathematics', 'lecture' => 3, 'laboratory' => 0],
            ['code' => 'ENG101', 'title' => 'English Communication', 'lecture' => 3, 'laboratory' => 0],
            ['code' => 'SCI101', 'title' => 'General Science', 'lecture' => 3, 'laboratory' => 0],
            ['code' => 'PE101', 'title' => 'Physical Education', 'lecture' => 1, 'laboratory' => 1],
            ['code' => 'HIST101', 'title' => 'Philippine History', 'lecture' => 3, 'laboratory' => 0],
        ];

        /** @var array{code: string, title: string, lecture: int, laboratory: int} $entry */
        $entry = $this->faker->randomElement($catalog);

        $course = Course::query()->inRandomOrder()->first();
        $courseId = $course ? $course->id : Course::factory()->create()->id;

        $lecture = $entry['lecture'];
        $laboratory = $entry['laboratory'];

        return [
            'code' => $entry['code'].'-'.$this->faker->unique()->numberBetween(1, 99999),
            'title' => $entry['title'],
            'classification' => SubjectEnrolledEnum::INTERNAL->value,
            'units' => $lecture + $laboratory,
            'lecture' => $lecture,
            'laboratory' => $laboratory,
            'pre_riquisite' => null,
            'academic_year' => $this->faker->numberBetween(1, 4),
            'semester' => $this->faker->numberBetween(1, 2),
            'course_id' => $courseId,
            'group' => $this->faker->optional()->randomElement(['A', 'B', 'C']),
            'is_credited' => true,
        ];
    }
}
