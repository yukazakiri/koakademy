<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationShareService
{
    /**
     * Transform notifications for frontend consumption.
     *
     * @return array<int, array<string, mixed>>
     */
    public function transformNotifications(?User $user): array
    {
        if (! $user instanceof User) {
            return [];
        }

        return $user->notifications()
            ->latest()
            ->take(10)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->transformNotification($notification))
            ->toArray();
    }

    /**
     * Get unread notifications count.
     */
    public function getUnreadCount(?User $user): int
    {
        if (! $user instanceof User) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    /**
     * Transform a single notification for frontend.
     *
     * @return array<string, mixed>
     */
    private function transformNotification(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = $notification->data ?? [];

        $actions = $this->extractActions($data);

        if ($actions === [] && (($data['action_url'] ?? null) || ($data['download_url'] ?? null))) {
            $actions = [
                [
                    'name' => 'action',
                    'label' => (string) ($data['action_text'] ?? 'View'),
                    'url' => $data['action_url'] ?? $data['download_url'] ?? null,
                    'color' => null,
                    'icon' => null,
                    'shouldOpenInNewTab' => false,
                ],
            ];
        }

        $actionUrl = $data['action_url'] ?? $data['download_url'] ?? null;

        if (! $actionUrl && count($actions) === 1) {
            $actionUrl = $actions[0]['url'] ?? null;
        }

        return [
            'id' => $notification->id,
            'type' => class_basename($notification->type),
            'title' => $data['title'] ?? 'Notification',
            'message' => $data['message'] ?? $data['body'] ?? '',
            'icon' => $data['icon'] ?? 'heroicon-o-bell',
            'notificationType' => $data['type'] ?? $data['status'] ?? 'info',
            'actionUrl' => $actionUrl,
            'actions' => $actions,
            'readAt' => format_timestamp($notification->read_at),
            'createdAt' => format_timestamp($notification->created_at),
        ];
    }

    /**
     * Extract actions from notification data.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function extractActions(array $data): array
    {
        return collect($data['actions'] ?? [])
            ->filter(fn ($action): bool => is_array($action))
            ->map(fn (array $action): array => [
                'name' => (string) ($action['name'] ?? $action['id'] ?? ''),
                'label' => (string) ($action['label'] ?? $action['name'] ?? 'View'),
                'url' => $action['url'] ?? null,
                'color' => $action['color'] ?? null,
                'icon' => $action['icon'] ?? null,
                'shouldOpenInNewTab' => (bool) ($action['shouldOpenInNewTab'] ?? $action['shouldOpenUrlInNewTab'] ?? $action['openUrlInNewTab'] ?? false),
            ])
            ->values()
            ->all();
    }
}
