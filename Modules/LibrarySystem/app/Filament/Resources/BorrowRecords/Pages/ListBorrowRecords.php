<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\BorrowRecordResource;

final class ListBorrowRecords extends ListRecords
{
    protected static string $resource = BorrowRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
