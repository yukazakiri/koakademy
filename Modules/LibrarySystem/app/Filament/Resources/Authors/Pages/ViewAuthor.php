<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\Authors\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\LibrarySystem\Filament\Resources\Authors\AuthorResource;

final class ViewAuthor extends ViewRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
