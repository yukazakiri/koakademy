<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\BorrowRecordResource;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\BorrowRecord;
use Modules\LibrarySystem\Services\LibraryBorrowStockService;

final class CreateBorrowRecord extends CreateRecord
{
    protected static string $resource = BorrowRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['status'] ?? 'borrowed') === 'returned') {
            $data['returned_at'] = $data['returned_at'] ?? now()->toDateTimeString();
        } else {
            $data['returned_at'] = null;
        }

        $stockService = app(LibraryBorrowStockService::class);
        $book = Book::findOrFail($data['book_id']);

        if (! $stockService->canBorrow($book, $data['status'] ?? 'borrowed')) {
            Notification::make()
                ->danger()
                ->title('No available copies')
                ->body('This book has no available copies left.')
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var BorrowRecord $record */
        $record = $this->record;
        app(LibraryBorrowStockService::class)->recordCreated($record);
    }
}
