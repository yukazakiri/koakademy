<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;

final class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;
}
