<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventorySuppliers\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Filament\Resources\InventorySuppliers\InventorySupplierResource;

final class ListInventorySuppliers extends ListRecords
{
    protected static string $resource = InventorySupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
