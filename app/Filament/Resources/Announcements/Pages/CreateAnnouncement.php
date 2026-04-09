<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;
}
