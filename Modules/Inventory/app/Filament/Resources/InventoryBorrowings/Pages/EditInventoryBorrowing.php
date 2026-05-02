<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryBorrowings\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Inventory\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;

final class EditInventoryBorrowing extends EditRecord
{
    protected static string $resource = InventoryBorrowingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
