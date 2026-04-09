<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements\Pages;

use App\Filament\Resources\InventoryStockMovements\InventoryStockMovementResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryStockMovement extends CreateRecord
{
    protected static string $resource = InventoryStockMovementResource::class;
}
