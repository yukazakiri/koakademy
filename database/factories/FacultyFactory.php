<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Faculty>
 */
final class FacultyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = ['CCS', 'CBA', 'CTE', 'CHTM'];
        $genders = ['male', 'female'];

        return [
            'id' => $this->faker->uuid(),
            'faculty_id_number' => $this->faker->unique()->numerify('F######'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'middle_name' => $this->faker->optional()->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'), // Default password
            'phone_number' => $this->faker->phoneNumber(),
            'department' => $this->faker->randomElement($departments),
            'office_hours' => $this->faker->optional()->text(100),
            'birth_date' => $this->faker->date(),
            'address_line1' => $this->faker->address(),
            'biography' => $this->faker->optional()->paragraph(),
            'education' => $this->faker->optional()->text(200),
            'courses_taught' => $this->faker->optional()->text(150),
            'photo_url' => $this->faker->optional()->imageUrl(400, 400, 'people'),
            'status' => 'active',
            'gender' => $this->faker->randomElement($genders),
            'age' => $this->faker->numberBetween(25, 65),
        ];
    }

    /**
     * A record the school has stood down.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'inactive',
        ]);
    }

    /**
     * A record for a faculty member who is temporarily away.
     */
    public function onLeave(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'on_leave',
        ]);
    }
}
