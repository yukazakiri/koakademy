<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings\Pages;

use App\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListInventoryBorrowings extends ListRecords
{
    protected static string $resource = InventoryBorrowingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
