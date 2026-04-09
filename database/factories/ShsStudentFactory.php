<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ShsStudent>
 */
final class ShsStudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_lrn' => fake()->unique()->numerify('##########'),
            'fullname' => fake()->name(),
            'civil_status' => fake()->randomElement(['Single', 'Married', 'Divorced', 'Widowed']),
            'religion' => fake()->optional()->word(),
            'nationality' => 'Filipino',
            'birthdate' => fake()->dateTimeBetween('-18 years', '-14 years'),
            'guardian_name' => fake()->name(),
            'guardian_contact' => fake()->phoneNumber(),
            'student_contact' => fake()->phoneNumber(),
            'complete_address' => fake()->address(),
            'grade_level' => fake()->randomElement(['Grade 11', 'Grade 12']),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'email' => fake()->unique()->safeEmail(),
            'remarks' => fake()->optional()->sentence(),
            'strand_id' => \App\Models\ShsStrand::factory(),
            'track_id' => \App\Models\ShsTrack::factory(),
        ];
    }
}
