<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryCategories\Pages;

use App\Filament\Resources\InventoryCategories\InventoryCategoryResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryCategory extends CreateRecord
{
    protected static string $resource = InventoryCategoryResource::class;
}
