<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateStudent extends CreateRecord
{
    protected static string $resource = StudentResource::class;
}
