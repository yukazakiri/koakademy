<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventorySuppliers\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Inventory\Filament\Resources\InventorySuppliers\InventorySupplierResource;

final class EditInventorySupplier extends EditRecord
{
    protected static string $resource = InventorySupplierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
