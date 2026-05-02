<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryAmendments\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Inventory\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;

final class ListInventoryAmendments extends ListRecords
{
    protected static string $resource = InventoryAmendmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
