<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Resources\Rooms\Schemas\RoomForm;
use App\Http\Requests\Administrators\RoomRequest;
use App\Models\Room;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorRoomManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $type = $request->input('type');

        $rooms = Room::query()
            ->with('school')
            ->withCount(['classes', 'schedules'])
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where(function ($nested) use ($term): void {
                    $nested->whereLike('name', "%{$term}%")
                        ->orWhereLike('class_code', "%{$term}%")
                        ->orWhereLike('building', "%{$term}%")
                        ->orWhereLike('type', "%{$term}%");
                });
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->when(is_string($type) && $type !== '' && $type !== 'all', function ($query) use ($type): void {
                $query->where('type', $type);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => $room->id,
                'school_id' => $room->school_id,
                'name' => $room->name,
                'class_code' => $room->class_code,
                'type' => $room->type ?? 'lecture',
                'capacity' => $room->capacity ?? 40,
                'building' => $room->building,
                'is_active' => (bool) $room->is_active,
                'school' => $room->school ? [
                    'id' => $room->school->id,
                    'name' => $room->school->name,
                ] : null,
                'classes_count' => $room->classes_count,
                'schedules_count' => $room->schedules_count,
                'created_at' => format_timestamp($room->created_at),
            ]);

        $stats = [
            'total' => Room::query()->count(),
            'active' => Room::query()->where('is_active', true)->count(),
            'inactive' => Room::query()->where('is_active', false)->count(),
            'total_capacity' => (int) Room::query()->where('is_active', true)->sum('capacity'),
        ];

        return Inertia::render('administrators/rooms/index', [
            'user' => $this->getUserProps(),
            'rooms' => $rooms,
            'stats' => $stats,
            'schools' => $this->getSchools(),
            'roomTypes' => $this->getRoomTypes(),
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'status' => is_string($status) ? $status : null,
                'type' => is_string($type) ? $type : null,
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/rooms/edit', [
            'user' => $this->getUserProps(),
            'room' => null,
            'schools' => $this->getSchools(),
            'roomTypes' => $this->getRoomTypes(),
        ]);
    }

    public function store(RoomRequest $request): RedirectResponse
    {
        $room = Room::create($request->validated());

        return redirect()
            ->route('administrators.rooms.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Room {$room->name} created successfully.",
            ]);
    }

    public function edit(Room $room): Response
    {
        return Inertia::render('administrators/rooms/edit', [
            'user' => $this->getUserProps(),
            'room' => [
                'id' => $room->id,
                'school_id' => $room->school_id,
                'name' => $room->name,
                'class_code' => $room->class_code,
                'type' => $room->type ?? 'lecture',
                'capacity' => $room->capacity ?? 40,
                'building' => $room->building,
                'is_active' => (bool) $room->is_active,
            ],
            'schools' => $this->getSchools(),
            'roomTypes' => $this->getRoomTypes(),
        ]);
    }

    public function update(RoomRequest $request, Room $room): RedirectResponse
    {
        $room->update($request->validated());

        return redirect()
            ->route('administrators.rooms.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Room {$room->name} updated successfully.",
            ]);
    }

    public function toggleStatus(Room $room): RedirectResponse
    {
        $room->is_active = ! $room->is_active;
        $room->save();

        $stateLabel = $room->is_active ? 'activated' : 'deactivated';

        return redirect()
            ->route('administrators.rooms.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Room {$room->name} {$stateLabel} successfully.",
            ]);
    }

    public function destroy(Room $room): RedirectResponse
    {
        $name = $room->name;
        $room->delete();

        return redirect()
            ->route('administrators.rooms.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Room {$name} deleted successfully.",
            ]);
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getSchools(): array
    {
        return School::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (School $school): array => [
                'id' => $school->id,
                'name' => $school->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getRoomTypes(): array
    {
        $types = [];
        foreach (RoomForm::roomTypeOptions() as $value => $label) {
            $types[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $types;
    }

    /**
     * @return array<string, mixed>
     */
    private function getUserProps(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }
}
