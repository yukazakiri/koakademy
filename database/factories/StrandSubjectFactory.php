<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StrandSubject>
 */
final class StrandSubjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Create a strand first to get its name for code generation
        $strand = \App\Models\ShsStrand::factory()->create();
        $strandName = $strand->strand_name;

        // Generate strand code
        $strandMap = [
            'STEM' => 'STM',
            'ABM' => 'ABM',
            'HUMSS' => 'HMS',
            'GAS' => 'GAS',
            'ICT' => 'ICT',
            'HOME ECONOMICS' => 'HME',
            'INDUSTRIAL ARTS' => 'INA',
            'AGRI-FISHERY ARTS' => 'AGR',
        ];

        $strandCode = $strandMap[$strandName] ?? mb_strtoupper(mb_substr((string) $strandName, 0, 3));
        $gradeYear = fake()->randomElement([11, 12]);
        $semester = fake()->randomElement([1, 2]);
        $counter = fake()->numberBetween(1, 999);

        $code = sprintf('%s%d%d%03d', $strandCode, $gradeYear, $semester, $counter);

        return [
            'code' => $code,
            'title' => fake()->sentence(nbWords: 4),
            'description' => fake()->optional()->paragraph(),
            'grade_year' => $gradeYear,
            'semester' => $semester,
            'strand_id' => $strand->id,
        ];
    }
}
