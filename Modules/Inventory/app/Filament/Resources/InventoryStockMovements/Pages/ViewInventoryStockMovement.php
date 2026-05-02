<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryStockMovements\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;

final class ViewInventoryStockMovement extends ViewRecord
{
    protected static string $resource = InventoryStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
