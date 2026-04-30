<?php

declare(strict_types=1);

use App\Models\Classes;
use App\Models\GeneralSetting;
use App\Models\Room;
use App\Models\Schedule;
use App\Rules\ScheduleOverlapRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

it('does not throw for uuid schedule ids in overlap validation payload', function (): void {
    $room = Room::factory()->createOne();

    $validator = Validator::make(
        [
            'schedules' => [
                [
                    'id' => '8c8425eb-518c-4d41-b90d-898eaf853f74',
                    'day_of_week' => 'Monday',
                    'start_time' => '08:00',
                    'end_time' => '09:00',
                    'room_id' => $room->id,
                ],
            ],
        ],
        [
            'schedules' => ['required', 'array', new ScheduleOverlapRule],
        ]
    );

    expect(fn (): bool => $validator->passes())->not->toThrow(Illuminate\Database\QueryException::class);
    expect($validator->passes())->toBeTrue();
});

it('does not flag a conflict against schedules from the same class when excluded class id is provided', function (): void {
    $room = Room::factory()->createOne();
    $class = Classes::factory()->createOne(['semester' => 1]);

    Schedule::factory()->createOne([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '08:00',
        'end_time' => '10:00',
    ]);

    $validator = Validator::make(
        [
            'schedules' => [
                [
                    'day_of_week' => 'Wednesday',
                    'start_time' => '08:00',
                    'end_time' => '10:00',
                    'room_id' => $room->id,
                ],
            ],
        ],
        [
            'schedules' => ['required', 'array', new ScheduleOverlapRule($class->id)],
        ]
    );

    expect($validator->passes())->toBeTrue();
});

it('does not flag a conflict against schedules from a different school year', function (): void {
    Cache::forget('general_settings_id');

    GeneralSetting::factory()->createOne([
        'school_starting_date' => '2025-08-01',
        'school_ending_date' => '2026-05-31',
        'semester' => 1,
    ]);

    $room = Room::factory()->createOne();
    $existingClass = Classes::factory()->createOne([
        'school_year' => '2025 - 2026',
        'semester' => 1,
    ]);

    Schedule::factory()->createOne([
        'class_id' => $existingClass->id,
        'room_id' => $room->id,
        'day_of_week' => 'Friday',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    $validator = Validator::make(
        [
            'school_year' => '2026 - 2027',
            'semester' => 1,
            'schedules' => [
                [
                    'day_of_week' => 'Friday',
                    'start_time' => '09:30',
                    'end_time' => '10:30',
                    'room_id' => $room->id,
                ],
            ],
        ],
        [
            'school_year' => ['required', 'string'],
            'semester' => ['required'],
            'schedules' => ['required', 'array', new ScheduleOverlapRule],
        ]
    );

    expect($validator->passes())->toBeTrue();
});
