<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements\Pages;

use App\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

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
