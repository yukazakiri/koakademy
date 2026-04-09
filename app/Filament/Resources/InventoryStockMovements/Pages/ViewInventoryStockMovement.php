<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements\Pages;

use App\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
