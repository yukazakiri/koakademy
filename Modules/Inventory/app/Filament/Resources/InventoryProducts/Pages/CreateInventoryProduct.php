<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryProducts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\InventoryProducts\InventoryProductResource;

final class CreateInventoryProduct extends CreateRecord
{
    protected static string $resource = InventoryProductResource::class;
}
