<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Authors\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\LibrarySystem\Filament\Resources\Authors\AuthorResource;

final class CreateAuthor extends CreateRecord
{
    protected static string $resource = AuthorResource::class;
}
