<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;

final class ListBooks extends ListRecords
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
