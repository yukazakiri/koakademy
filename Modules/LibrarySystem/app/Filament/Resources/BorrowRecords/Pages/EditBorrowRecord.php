<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Modules\LibrarySystem\Filament\Resources\BorrowRecords\BorrowRecordResource;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\BorrowRecord;
use Modules\LibrarySystem\Services\LibraryBorrowStockService;

final class EditBorrowRecord extends EditRecord
{
    protected static string $resource = BorrowRecordResource::class;

    protected ?int $originalBookId = null;

    protected ?string $originalStatus = null;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->after(fn (BorrowRecord $record) => app(LibraryBorrowStockService::class)->recordDeleted($record)),
            ForceDeleteAction::make()
                ->after(fn (BorrowRecord $record) => app(LibraryBorrowStockService::class)->recordDeleted($record)),
            RestoreAction::make()
                ->after(fn (BorrowRecord $record) => app(LibraryBorrowStockService::class)->recordRestored($record)),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['status'] ?? 'borrowed') === 'returned') {
            $data['returned_at'] = $data['returned_at'] ?? now()->toDateTimeString();
        } else {
            $data['returned_at'] = null;
        }

        /** @var BorrowRecord $record */
        $record = $this->record;
        $this->originalBookId = (int) $record->book_id;
        $this->originalStatus = (string) $record->status;

        $newBook = (int) $record->book_id === (int) $data['book_id']
            ? $record->book
            : Book::findOrFail($data['book_id']);

        $stockService = app(LibraryBorrowStockService::class);

        if (! $newBook || ! $stockService->canUpdateBorrow($record, $newBook, $data['status'])) {
            Notification::make()
                ->danger()
                ->title('No available copies')
                ->body('This book has no available copies left.')
                ->send();

            $this->halt();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var BorrowRecord $record */
        $record = $this->record;
        app(LibraryBorrowStockService::class)->recordUpdated(
            $record,
            $this->originalBookId,
            $this->originalStatus ?? $record->status,
        );
    }
}
