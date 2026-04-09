<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryAmendments\Pages;

use App\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
