<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings\Pages;

use App\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
