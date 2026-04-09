<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Categories\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LibrarySystem\Filament\Resources\Categories\CategoryResource;

final class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
