<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Announcement;

final class AnnouncementShareService
{
    /**
     * Get active announcements for frontend.
     *
     * @return array<int, array{id: int, title: string, content: string, type: string}>
     */
    public function getActiveAnnouncements(): array
    {
        return Announcement::query()
            ->active()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'type' => $announcement->type,
            ])
            ->toArray();
    }
}
