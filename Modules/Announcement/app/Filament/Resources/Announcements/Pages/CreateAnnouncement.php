<?php

declare(strict_types=1);

namespace Modules\Announcement\Filament\Resources\Announcements\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Announcement\Filament\Resources\Announcements\AnnouncementResource;

final class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;
}
