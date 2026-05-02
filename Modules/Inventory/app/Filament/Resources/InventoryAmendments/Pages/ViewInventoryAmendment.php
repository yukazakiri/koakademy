<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryAmendments\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Inventory\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;

final class ViewInventoryAmendment extends ViewRecord
{
    protected static string $resource = InventoryAmendmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
