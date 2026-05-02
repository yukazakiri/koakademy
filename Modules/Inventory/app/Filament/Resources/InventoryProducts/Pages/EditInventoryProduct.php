<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryProducts\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Inventory\Filament\Resources\InventoryProducts\InventoryProductResource;

final class EditInventoryProduct extends EditRecord
{
    protected static string $resource = InventoryProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
