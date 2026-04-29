<?php

declare(strict_types=1);

namespace Tests\Feature\Administrators;

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScheduleUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $course = Course::factory()->create();
        $subject = Subject::factory()->create();
        $room = Room::factory()->create();
        $faculty = Faculty::factory()->create();

        $class = Classes::factory()->create([
            'subject_id' => $subject->id,
            'faculty_id' => $faculty->id,
            'room_id' => $room->id,
        ]);

        $this->schedule = Schedule::factory()->create([
            'class_id' => $class->id,
            'room_id' => $room->id,
            'day_of_week' => 'Monday',
            'start_time' => '09:00:00',
            'end_time' => '10:30:00',
        ]);
    }

    public function test_admin_can_update_schedule_time_and_day(): void
    {
        $response = $this->actingAs($this->admin)->patchJson(route('administrators.scheduling-analytics.schedules.update', $this->schedule->id), [
            'day_of_week' => 'Wednesday',
            'start_time' => '13:00',
            'end_time' => '14:30',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'schedule' => [
                'day_of_week',
                'start_time',
                'end_time',
                'time_range',
                'room',
                'room_id',
            ],
            'conflicts',
        ]);

        $response->assertJsonPath('schedule.day_of_week', 'Wednesday');
        $response->assertJsonPath('schedule.start_time', '01:00 PM');
        $response->assertJsonPath('schedule.end_time', '02:30 PM');

        $this->assertDatabaseHas('schedule', [
            'id' => $this->schedule->id,
            'day_of_week' => 'Wednesday',
            'start_time' => '13:00:00',
            'end_time' => '14:30:00',
        ]);
    }

    public function test_cannot_update_schedule_with_invalid_data(): void
    {
        $response = $this->actingAs($this->admin)->patchJson(route('administrators.scheduling-analytics.schedules.update', $this->schedule->id), [
            'day_of_week' => 'Sunday', // Invalid day
            'start_time' => '14:30',
            'end_time' => '13:00', // End before start
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['day_of_week', 'end_time']);
    }

    public function test_non_admin_cannot_update_schedule(): void
    {
        $student = User::factory()->create([
            'role' => UserRole::Student,
        ]);

        $response = $this->actingAs($student)->patchJson(route('administrators.scheduling-analytics.schedules.update', $this->schedule->id), [
            'day_of_week' => 'Wednesday',
            'start_time' => '13:00',
            'end_time' => '14:30',
        ]);

        $response->assertForbidden();
    }
}
