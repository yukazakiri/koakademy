<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

use function Pest\Laravel\actingAs;

it('allows faculty to update class schedules', function () {
    $room = Room::factory()->createOne();
    $otherRoom = Room::factory()->createOne();

    $faculty = Faculty::factory()->createOne();
    $user = User::factory()->createOne([
        'email' => $faculty->email,
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'F123456',
    ]);

    $class = Classes::factory()->createOne([
        'faculty_id' => $faculty->id,
        'room_id' => $room->id,
    ]);

    $schedule = Schedule::factory()->createOne([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    expect($user->isFaculty())->toBeTrue();
    expect($class->faculty_id)->toBe($faculty->id);
    expect(Faculty::query()->where('email', $user->email)->exists())->toBeTrue();

    config()->set('auth.defaults.guard', 'web');
    Auth::shouldUse('web');

    /** @var Illuminate\Contracts\Auth\Authenticatable $authUser */
    $authUser = $user;

    $response = actingAs($authUser, 'web')
        ->put(route('faculty.classes.schedules.update', $class->id), [
            'schedules' => [
                [
                    'id' => $schedule->id,
                    'day_of_week' => 'Wednesday',
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                    'room_id' => $otherRoom->id,
                ],
            ],
        ])
        ->assertRedirect();

    $schedule->refresh();

    expect($schedule->day_of_week)->toBe('Wednesday');
    expect($schedule->start_time->format('H:i'))->toBe('08:00');
    expect($schedule->end_time->format('H:i'))->toBe('09:00');
    expect($schedule->room_id)->toBe($otherRoom->id);
});
