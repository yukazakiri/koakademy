<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryAmendments\Pages;

use App\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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
