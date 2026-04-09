<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rooms\Pages;

use App\Filament\Resources\Rooms\RoomResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;
}
