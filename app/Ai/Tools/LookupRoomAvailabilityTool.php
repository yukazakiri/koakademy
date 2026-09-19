<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Room;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class LookupRoomAvailabilityTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Check classroom room availability, capacity, location, and existing scheduled classes by day of week or room name.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'room_name' => 'nullable|string',
            'day_of_week' => 'nullable|string',
            'limit' => 'nullable|integer',
        ]);

        $limit = min(50, max(1, $validated['limit'] ?? 15));

        $roomsQuery = Room::query()->with([
            'schedules.class.faculty',
            'classes',
        ])->limit($limit);

        if (filled($validated['room_name'] ?? null)) {
            $roomsQuery->where('name', 'like', "%{$validated['room_name']}%");
        }

        $rooms = $roomsQuery->get()->map(function (Room $room) use ($validated) {
            $schedulesQuery = $room->schedules;

            if (filled($validated['day_of_week'] ?? null)) {
                $day = mb_strtolower($validated['day_of_week']);
                $schedulesQuery = $schedulesQuery->filter(fn ($s) => mb_strtolower((string) $s->day_of_week) === $day);
            }

            $bookedSlots = $schedulesQuery->map(function ($s) {
                return [
                    'day' => $s->day_of_week,
                    'time' => "{$s->formatted_start_time} - {$s->formatted_end_time}",
                    'class' => $s->class ? "{$s->class->subject_code} ({$s->class->section})" : 'N/A',
                    'instructor' => $s->class?->faculty?->name ?? 'TBA',
                ];
            })->values()->all();

            return [
                'room_id' => $room->id,
                'name' => $room->name,
                'building' => $room->building ?? 'Main Campus',
                'capacity' => $room->capacity ?? 40,
                'type' => $room->type ?? 'Lecture Room',
                'total_scheduled_slots' => count($bookedSlots),
                'schedules' => $bookedSlots,
            ];
        })->values()->all();

        return json_encode([
            'count' => count($rooms),
            'day_filter' => $validated['day_of_week'] ?? 'All Days',
            'rooms' => $rooms,
        ], JSON_PRETTY_PRINT);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'room_name' => $schema->string()->description('Optional room name or number to filter by (e.g. 204, Lab 1)'),
            'day_of_week' => $schema->string()->description('Optional day of week filter (e.g. Monday, Tuesday)'),
            'limit' => $schema->integer()->description('Max rooms to list (default 15)'),
        ];
    }
}
