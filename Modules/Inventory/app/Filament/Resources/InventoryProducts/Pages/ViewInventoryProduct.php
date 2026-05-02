<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryProducts\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\InventoryProducts\InventoryProductResource;

final class ViewInventoryProduct extends ViewRecord
{
    protected static string $resource = InventoryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
