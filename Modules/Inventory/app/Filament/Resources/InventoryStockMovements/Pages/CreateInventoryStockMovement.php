<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryStockMovements\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;

final class CreateInventoryStockMovement extends CreateRecord
{
    protected static string $resource = InventoryStockMovementResource::class;
}
