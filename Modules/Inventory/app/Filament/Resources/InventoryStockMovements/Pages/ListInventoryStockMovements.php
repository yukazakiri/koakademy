<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryStockMovements\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;

final class ListInventoryStockMovements extends ListRecords
{
    protected static string $resource = InventoryStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
