<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryAmendments\Pages;

use App\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

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
