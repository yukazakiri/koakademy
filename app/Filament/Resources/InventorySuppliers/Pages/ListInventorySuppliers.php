<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers\Pages;

use App\Filament\Resources\InventorySuppliers\InventorySupplierResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
