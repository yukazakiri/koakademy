<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Books\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;

// use Modules\Librarysystem\Resources\Books\BookResource;

final class ViewBook extends ViewRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
