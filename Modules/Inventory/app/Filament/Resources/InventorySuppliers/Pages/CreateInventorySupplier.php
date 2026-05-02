<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventorySuppliers\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\InventorySuppliers\InventorySupplierResource;

final class CreateInventorySupplier extends CreateRecord
{
    protected static string $resource = InventorySupplierResource::class;
}
