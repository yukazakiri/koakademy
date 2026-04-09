<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryProducts\Pages;

use App\Filament\Resources\InventoryProducts\InventoryProductResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryProduct extends CreateRecord
{
    protected static string $resource = InventoryProductResource::class;
}
