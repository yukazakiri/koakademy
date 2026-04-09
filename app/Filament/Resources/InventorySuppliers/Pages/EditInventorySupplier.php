<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers\Pages;

use App\Filament\Resources\InventorySuppliers\InventorySupplierResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

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
