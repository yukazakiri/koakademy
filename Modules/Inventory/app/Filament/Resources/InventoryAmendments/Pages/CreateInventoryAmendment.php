<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryAmendments\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Inventory\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;

final class CreateInventoryAmendment extends CreateRecord
{
    protected static string $resource = InventoryAmendmentResource::class;
}
