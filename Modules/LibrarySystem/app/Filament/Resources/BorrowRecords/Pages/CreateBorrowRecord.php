<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\BorrowRecordResource;

final class CreateBorrowRecord extends CreateRecord
{
    protected static string $resource = BorrowRecordResource::class;
}
