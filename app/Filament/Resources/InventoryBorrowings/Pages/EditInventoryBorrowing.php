<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings\Pages;

use App\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

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
