<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryProducts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Filament\Resources\InventoryProducts\InventoryProductResource;

final class ListInventoryProducts extends ListRecords
{
    protected static string $resource = InventoryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
