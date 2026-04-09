<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Categories\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\LibrarySystem\Filament\Resources\Categories\CategoryResource;

final class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
