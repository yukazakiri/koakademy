<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryBorrowings\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;

final class CreateInventoryBorrowing extends CreateRecord
{
    protected static string $resource = InventoryBorrowingResource::class;
}
