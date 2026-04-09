<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
final class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roomNames = [
            '101', '102', '103', '104', '105',
            '201', '202', '203', '204', '205',
            '301', '302', '303', '304', '305',
            '401', '402', '403', '404', '405',
            'Lab1', 'Lab2', 'Lab3', 'Gym', 'Auditorium',
        ];

        $classCodes = ['LEC', 'LAB', 'GYM', 'AUD', 'CONF'];

        return [
            'name' => $this->faker->unique()->randomElement($roomNames),
            'class_code' => $this->faker->randomElement($classCodes),
        ];
    }
}
