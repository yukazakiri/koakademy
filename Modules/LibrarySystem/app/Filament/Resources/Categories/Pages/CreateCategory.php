<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Categories\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\LibrarySystem\Filament\Resources\Categories\CategoryResource;

final class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
