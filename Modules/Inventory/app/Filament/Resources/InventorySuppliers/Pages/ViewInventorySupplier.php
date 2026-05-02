<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventorySuppliers\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\InventorySuppliers\InventorySupplierResource;

final class ViewInventorySupplier extends ViewRecord
{
    protected static string $resource = InventorySupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
