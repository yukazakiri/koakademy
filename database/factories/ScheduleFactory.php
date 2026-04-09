<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Schedule>
 */
final class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        // Generate random start time between 7:00 AM and 6:00 PM
        $startHour = $this->faker->numberBetween(7, 18);
        $startMinute = $this->faker->randomElement([0, 30]);
        $startTime = Carbon::createFromTime($startHour, $startMinute);

        // Generate end time 1.5 to 3 hours after start time
        $duration = $this->faker->randomElement([90, 120, 150, 180]); // minutes
        $endTime = $startTime->copy()->addMinutes($duration);

        return [
            'day_of_week' => $this->faker->randomElement($daysOfWeek),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'room_id' => \App\Models\Room::factory(),
            'class_id' => \App\Models\Classes::factory(),
        ];
    }
}
