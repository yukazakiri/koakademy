<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\BorrowRecordResource;

final class ViewBorrowRecord extends ViewRecord
{
    protected static string $resource = BorrowRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
