<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ClassAttendanceSession;
use Illuminate\Database\Eloquent\Factories\Factory;

final class ClassAttendanceSessionFactory extends Factory
{
    protected $model = ClassAttendanceSession::class;

    public function definition()
    {
        $class = \App\Models\Classes::factory()->create();
        $schedule = $class->schedules()->create([
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'room_id' => \App\Models\Room::factory()->create()->id,
        ]);

        return [
            'class_id' => $class->id,
            'schedule_id' => $schedule->id,
            'session_date' => now()->next('Monday')->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '09:00:00',
            'topic' => $this->faker->sentence(),
            'taken_by' => $class->faculty_id,
            'is_locked' => false,
            'is_no_meeting' => false,
        ];
    }
}
