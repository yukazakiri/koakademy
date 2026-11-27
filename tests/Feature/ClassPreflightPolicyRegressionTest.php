<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Room;
use App\Models\User;

it('blocks a Sunday preflight when Sunday scheduling is disabled', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

    $this->postJson(route('administrators.classes.workspace.preflight'), [
        'classification' => 'college',
        'school_year' => '2026 - 2027',
        'semester' => '1',
        'maximum_slots' => 40,
        'schedules' => [[
            'day_of_week' => 'Sunday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_id' => null,
        ]],
    ])->assertSuccessful()
        ->assertJsonPath('can_publish', false)
        ->assertJsonPath('conflicts.0.message', 'Scheduling is not allowed on Sunday.');
});

it('keeps an explicitly unassigned meeting separate from the default room', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $room = Room::factory()->create(['is_active' => true]);

    $this->postJson(route('administrators.classes.workspace.preflight'), [
        'classification' => 'college',
        'school_year' => '2026 - 2027',
        'semester' => '1',
        'maximum_slots' => 40,
        'room_id' => $room->id,
        'schedules' => [[
            'day_of_week' => 'Monday',
            'start_time' => '09:00',
            'end_time' => '10:00',
            'room_id' => null,
        ]],
    ])->assertSuccessful()->assertJsonPath('room_checks.0.room_id', null);
});
