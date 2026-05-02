<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryCategories\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\InventoryCategories\InventoryCategoryResource;

final class CreateInventoryCategory extends CreateRecord
{
    protected static string $resource = InventoryCategoryResource::class;
}
