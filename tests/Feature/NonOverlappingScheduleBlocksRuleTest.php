<?php

declare(strict_types=1);

use App\Rules\NonOverlappingScheduleBlocksRule;
use Illuminate\Support\Facades\Validator;

it('rejects duplicate schedule blocks', function (): void {
    $validator = Validator::make([
        'schedules' => [
            ['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => 1],
            ['day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => 1],
        ],
    ], [
        'schedules' => [new NonOverlappingScheduleBlocksRule],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('schedules'))->toBe('Schedule blocks 1 and 2 are duplicates.');
});

it('rejects overlapping blocks for the same class even when rooms differ', function (): void {
    $validator = Validator::make([
        'schedules' => [
            ['day_of_week' => 'Wednesday', 'start_time' => '08:00', 'end_time' => '10:00', 'room_id' => 1],
            ['day_of_week' => 'Wednesday', 'start_time' => '09:30', 'end_time' => '11:00', 'room_id' => 2],
        ],
    ], [
        'schedules' => [new NonOverlappingScheduleBlocksRule],
    ]);

    expect($validator->passes())->toBeFalse()
        ->and($validator->errors()->first('schedules'))->toBe('Schedule blocks 1 and 2 overlap on Wednesday.');
});

it('allows adjacent blocks and matching times on different days', function (): void {
    $validator = Validator::make([
        'schedules' => [
            ['day_of_week' => 'Thursday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => 1],
            ['day_of_week' => 'Thursday', 'start_time' => '09:00', 'end_time' => '10:00', 'room_id' => 1],
            ['day_of_week' => 'Friday', 'start_time' => '08:00', 'end_time' => '09:00', 'room_id' => 1],
        ],
    ], [
        'schedules' => [new NonOverlappingScheduleBlocksRule],
    ]);

    expect($validator->passes())->toBeTrue();
});
