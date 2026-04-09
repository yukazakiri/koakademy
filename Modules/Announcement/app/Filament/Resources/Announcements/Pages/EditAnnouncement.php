<?php

declare(strict_types=1);

namespace Modules\Announcement\Filament\Resources\Announcements\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Announcement\Filament\Resources\Announcements\AnnouncementResource;

final class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_active'] = ($data['status'] ?? 'draft') === 'published';
        $data['starts_at'] = $data['starts_at'] ?? $data['published_at'] ?? null;
        $data['ends_at'] = $data['ends_at'] ?? $data['expires_at'] ?? null;
        $data['expires_at'] = $data['expires_at'] ?? $data['ends_at'] ?? null;
        $data['display_mode'] = $data['display_mode'] ?? 'banner';
        $data['requires_acknowledgment'] = $data['requires_acknowledgment'] ?? false;

        return $data;
    }
}
