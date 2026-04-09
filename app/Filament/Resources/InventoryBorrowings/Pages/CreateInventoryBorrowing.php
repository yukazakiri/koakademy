<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings\Pages;

use App\Filament\Resources\InventoryBorrowings\InventoryBorrowingResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryBorrowing extends CreateRecord
{
    protected static string $resource = InventoryBorrowingResource::class;
}
