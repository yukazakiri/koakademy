<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryCategories\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Inventory\Filament\Resources\InventoryCategories\InventoryCategoryResource;

final class EditInventoryCategory extends EditRecord
{
    protected static string $resource = InventoryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
