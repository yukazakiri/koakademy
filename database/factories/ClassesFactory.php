<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classes>
 */
final class ClassesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sections = ['A', 'B', 'C', 'D', 'E'];
        $classifications = ['college', 'shs'];
        $semesters = [1, 2];
        $academicYears = [1, 2, 3, 4];
        $schoolYears = ['2023 - 2024', '2024 - 2025', '2025 - 2026'];

        // Get existing course or create new one if none exists
        $course = \App\Models\Course::query()->inRandomOrder()->first();
        $courseId = $course ? $course->id : \App\Models\Course::factory()->create()->id;

        return [
            'subject_id' => \App\Models\Subject::factory(),
            'subject_code' => $this->faker->regexify('[A-Z]{2,3}[0-9]{3}'),
            'faculty_id' => \App\Models\Faculty::factory(),
            'academic_year' => $this->faker->randomElement($academicYears),
            'semester' => $this->faker->randomElement($semesters),
            'school_year' => $this->faker->randomElement($schoolYears),
            'course_codes' => [(string) $courseId],
            'section' => $this->faker->randomElement($sections),
            'room_id' => \App\Models\Room::factory(),
            'classification' => $this->faker->randomElement($classifications),
            'maximum_slots' => $this->faker->numberBetween(30, 50),
            'shs_track_id' => null,
            'shs_strand_id' => null,
            'grade_level' => null,
            'settings' => \App\Models\Classes::getDefaultSettings(),
        ];
    }
}
