<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryCategories\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Filament\Resources\InventoryCategories\InventoryCategoryResource;

final class ListInventoryCategories extends ListRecords
{
    protected static string $resource = InventoryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
