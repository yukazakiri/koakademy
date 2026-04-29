<?php

declare(strict_types=1);

use App\Models\Room;
use App\Rules\ScheduleOverlapRule;
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
