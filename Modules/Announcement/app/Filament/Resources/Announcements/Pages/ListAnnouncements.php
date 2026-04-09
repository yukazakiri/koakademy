<?php

declare(strict_types=1);

namespace Modules\Announcement\Filament\Resources\Announcements\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Announcement\Filament\Resources\Announcements\AnnouncementResource;

final class ListAnnouncements extends ListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->mutateFormDataUsing(function (array $data): array {
                    $authorId = auth()->id();
                    $publishedAt = $data['published_at'] ?? $data['starts_at'] ?? now();

                    $data['created_by'] = $authorId;
                    $data['is_global'] = $data['is_global'] ?? true;
                    $data['display_mode'] = $data['display_mode'] ?? 'banner';
                    $data['requires_acknowledgment'] = $data['requires_acknowledgment'] ?? false;
                    $data['is_active'] = ($data['status'] ?? 'draft') === 'published';
                    $data['starts_at'] = $data['starts_at'] ?? $publishedAt;
                    $data['expires_at'] = $data['expires_at'] ?? ($data['ends_at'] ?? null);
                    $data['ends_at'] = $data['ends_at'] ?? $data['expires_at'] ?? null;

                    return $data;
                }),
        ];
    }
}
