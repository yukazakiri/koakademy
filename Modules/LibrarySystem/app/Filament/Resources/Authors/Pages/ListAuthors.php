<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Authors\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LibrarySystem\Filament\Resources\Authors\AuthorResource;

final class ListAuthors extends ListRecords
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
