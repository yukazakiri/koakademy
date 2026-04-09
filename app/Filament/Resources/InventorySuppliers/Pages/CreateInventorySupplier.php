<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers\Pages;

use App\Filament\Resources\InventorySuppliers\InventorySupplierResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventorySupplier extends CreateRecord
{
    protected static string $resource = InventorySupplierResource::class;
}
