<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers\Pages;

use App\Filament\Resources\InventorySuppliers\InventorySupplierResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
