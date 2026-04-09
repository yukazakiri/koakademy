<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\LibrarySystem\Models\ResearchPaper;

final class ResearchPaperFactory extends Factory
{
    protected $model = ResearchPaper::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement(['capstone', 'thesis', 'research']);

        return [
            'title' => $this->faker->sentence(4),
            'type' => $type,
            'student_id' => null,
            'course_id' => null,
            'advisor_name' => $this->faker->optional()->name(),
            'contributors' => $this->faker->optional(0.4)->name(),
            'abstract' => $this->faker->optional()->paragraph(),
            'keywords' => $this->faker->optional()->words(5, true),
            'publication_year' => $this->faker->optional()->numberBetween(2015, (int) date('Y')),
            'document_url' => $this->faker->optional(0.4)->url(),
            'status' => $this->faker->randomElement(['draft', 'submitted', 'archived']),
            'is_public' => $this->faker->boolean(25),
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }
}
