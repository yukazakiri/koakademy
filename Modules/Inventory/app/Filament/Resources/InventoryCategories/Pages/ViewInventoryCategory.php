<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryCategories\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\InventoryCategories\InventoryCategoryResource;

final class ViewInventoryCategory extends ViewRecord
{
    protected static string $resource = InventoryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
