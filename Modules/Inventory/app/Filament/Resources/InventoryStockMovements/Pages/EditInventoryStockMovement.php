<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryStockMovements\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Inventory\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;

final class EditInventoryStockMovement extends EditRecord
{
    protected static string $resource = InventoryStockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
