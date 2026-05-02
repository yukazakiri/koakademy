<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryBorrowings\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;

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
