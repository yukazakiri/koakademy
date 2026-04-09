<?php

declare(strict_types=1);

namespace App\Filament\Resources\SanityContents\Pages;

use App\Filament\Resources\SanityContents\SanityContentResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateSanityContent extends CreateRecord
{
    protected static string $resource = SanityContentResource::class;
}
