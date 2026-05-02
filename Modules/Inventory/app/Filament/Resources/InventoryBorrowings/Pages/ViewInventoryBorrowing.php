<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryBorrowings\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;

final class ViewInventoryBorrowing extends ViewRecord
{
    protected static string $resource = InventoryBorrowingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
