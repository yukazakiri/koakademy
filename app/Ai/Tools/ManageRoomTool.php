<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Room;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

final class ManageRoomTool implements Tool
{
    use InteractsWithApprovals;

    public function description(): Stringable|string
    {
        return 'Create, update, or inspect classrooms and facility rooms. Modifications require confirmation.';
    }

    public function handle(Request $request): Stringable|string
    {
        $action = mb_strtolower((string) $request['action']);

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            'get' => $this->handleGet($request),
            default => json_encode(['error' => true, 'message' => "Unknown action '{$action}'. Supported: create, update, get."]),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'get'])->required()->description('Operation to execute on room records.'),
            'room_id' => $schema->integer()->description('Room database ID (for update/get).'),
            'name' => $schema->string()->description('Classroom name or number (e.g. "Room 301", "Computer Lab A").'),
            'class_code' => $schema->string()->description('Optional room code.'),
            'is_active' => $schema->boolean()->description('Whether room is active for scheduling.'),
        ];
    }

    protected function needsApproval(Request $request): Approval|bool
    {
        $action = mb_strtolower((string) ($request['action'] ?? ''));

        if ($action === 'get') {
            return false;
        }

        if ($action === 'create') {
            $name = $request['name'] ?? 'Classroom';

            return Approval::required("Create new classroom facility '{$name}'?");
        }

        if ($action === 'update') {
            $id = $request['room_id'] ?? ($request['name'] ?? 'Room');

            return Approval::required("Save updates to room '{$id}'?");
        }

        return false;
    }

    private function handleCreate(Request $request): string
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'class_code' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $room = Room::query()->create([
            'name' => mb_trim($validated['name']),
            'class_code' => $validated['class_code'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return json_encode([
            'success' => true,
            'action' => 'create',
            'message' => "Successfully created room {$room->name}.",
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'is_active' => $room->is_active,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleUpdate(Request $request): string
    {
        $room = $this->resolveRoom($request);
        if (! $room instanceof Room) {
            return json_encode(['error' => true, 'message' => 'Room not found.']);
        }

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'class_code' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'class_code' => $validated['class_code'] ?? null,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : null,
        ], fn ($val) => $val !== null);

        $room->update($updates);

        return json_encode([
            'success' => true,
            'action' => 'update',
            'message' => "Successfully updated room {$room->name}.",
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'is_active' => $room->is_active,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function handleGet(Request $request): string
    {
        $room = $this->resolveRoom($request);
        if (! $room instanceof Room) {
            return json_encode(['error' => true, 'message' => 'Room not found.']);
        }

        return json_encode([
            'found' => true,
            'id' => $room->id,
            'name' => $room->name,
            'is_active' => (bool) $room->is_active,
            'scheduled_classes_count' => $room->classes()->count(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function resolveRoom(Request $request): ?Room
    {
        $id = $request['room_id'] ?? null;
        if ($id && is_numeric($id)) {
            $found = Room::query()->find((int) $id);
            if ($found) {
                return $found;
            }
        }

        $name = $request['name'] ?? null;
        if ($name) {
            return Room::query()->where('name', 'like', "%{$name}%")->first();
        }

        return null;
    }
}
