<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryAmendments\Pages;

use App\Filament\Resources\InventoryAmendments\InventoryAmendmentResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateInventoryAmendment extends CreateRecord
{
    protected static string $resource = InventoryAmendmentResource::class;
}
