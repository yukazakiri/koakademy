<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryAmendments\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Inventory\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;

final class EditInventoryAmendment extends EditRecord
{
    protected static string $resource = InventoryAmendmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
