<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Concerns\AuthorizesMcpRequests;
use App\Models\Room;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Manage classroom facilities: create new rooms, update capacity or status, or inspect schedule allocations.')]
final class ManageRoomTool extends Tool
{
    use AuthorizesMcpRequests;

    public function handle(Request $request): ResponseFactory
    {
        $action = mb_strtolower((string) $request->get('action'));

        if ($action === 'get') {
            $user = $this->requireRead($request);
            $this->requirePermission($user, 'View:Room', 'You are not permitted to view rooms.');

            $room = $this->resolveRoom($request);
            if (! $room instanceof Room) {
                return Response::structured(['found' => false, 'message' => 'Room not found.']);
            }

            return Response::structured([
                'found' => true,
                'id' => $room->id,
                'name' => $room->name,
                'is_active' => (bool) $room->is_active,
                'scheduled_classes' => $room->classes()->count(),
            ]);
        }

        $user = $this->requireWrite($request);
        $this->requirePermission($user, 'Update:Room', 'You are not permitted to modify rooms.');

        return match ($action) {
            'create' => $this->handleCreate($request),
            'update' => $this->handleUpdate($request),
            default => Response::structured(['error' => true, 'message' => "Unsupported action '{$action}'."]),
        };
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['create', 'update', 'get'])->required()->description('Operation: create, update, get.'),
            'room_id' => $schema->integer()->description('Room ID.'),
            'name' => $schema->string()->description('Room name (e.g. Room 204).'),
            'class_code' => $schema->string()->description('Optional room code.'),
            'is_active' => $schema->boolean()->description('Whether room is active.'),
        ];
    }

    private function handleCreate(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'class_code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $room = Room::query()->create([
            'name' => mb_trim($validated['name']),
            'class_code' => $validated['class_code'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return Response::structured([
            'success' => true,
            'action' => 'create',
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'is_active' => $room->is_active,
            ],
        ]);
    }

    private function handleUpdate(Request $request): ResponseFactory
    {
        $room = $this->resolveRoom($request);
        if (! $room instanceof Room) {
            return Response::structured(['error' => true, 'message' => 'Room not found.']);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'class_code' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $updates = array_filter([
            'name' => $validated['name'] ?? null,
            'class_code' => $validated['class_code'] ?? null,
            'is_active' => isset($validated['is_active']) ? (bool) $validated['is_active'] : null,
        ], fn ($val) => $val !== null);

        $room->update($updates);

        return Response::structured([
            'success' => true,
            'action' => 'update',
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'is_active' => $room->is_active,
            ],
        ]);
    }

    private function resolveRoom(Request $request): ?Room
    {
        $id = $request->get('room_id');
        if ($id && is_numeric($id)) {
            $found = Room::query()->find((int) $id);
            if ($found) {
                return $found;
            }
        }

        $name = $request->get('name');
        if ($name) {
            return Room::query()->where('name', 'like', "%{$name}%")->first();
        }

        return null;
    }
}
