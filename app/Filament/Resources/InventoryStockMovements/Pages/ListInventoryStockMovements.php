<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements\Pages;

use App\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
