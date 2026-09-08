<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Services;

use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\BorrowRecord;

final class LibraryBorrowStockService
{
    public function borrowImpact(string $status): int
    {
        return in_array($status, ['borrowed', 'lost'], true) ? -1 : 0;
    }

    public function applyAvailabilityDelta(Book $book, int $delta): void
    {
        $book->available_copies = max(0, min($book->total_copies, $book->available_copies + $delta));

        if ($book->status !== 'maintenance') {
            $book->status = $book->available_copies > 0 ? 'available' : 'borrowed';
        }

        $book->save();
    }

    public function canBorrow(Book $book, string $status): bool
    {
        $impact = $this->borrowImpact($status);

        return ! ($impact < 0 && $book->available_copies <= 0);
    }

    public function canUpdateBorrow(BorrowRecord $record, Book $newBook, string $newStatus): bool
    {
        $originalBook = $record->book ?? Book::find($record->book_id);
        $originalImpact = $this->borrowImpact($record->status);
        $newImpact = $this->borrowImpact($newStatus);

        $availableCopies = $newBook->available_copies;

        if ($originalBook && $newBook->is($originalBook) && $originalImpact < 0) {
            $availableCopies += 1;
        }

        return ! ($newImpact < 0 && $availableCopies <= 0);
    }

    public function recordCreated(BorrowRecord $record): void
    {
        $impact = $this->borrowImpact($record->status);

        if ($impact !== 0) {
            $book = $record->book ?? Book::find($record->book_id);

            if ($book) {
                $this->applyAvailabilityDelta($book, $impact);
            }
        }
    }

    public function recordUpdated(BorrowRecord $record, ?int $originalBookId = null, ?string $originalStatus = null): void
    {
        $originalStatus = $originalStatus ?? (string) $record->getOriginal('status', $record->status);
        $originalBookId = $originalBookId ?? (int) $record->getOriginal('book_id', $record->book_id);
        $originalImpact = $this->borrowImpact($originalStatus);
        $newImpact = $this->borrowImpact($record->status);
        $currentBookId = (int) $record->book_id;

        if ($originalBookId === $currentBookId) {
            $delta = $newImpact - $originalImpact;

            if ($delta !== 0) {
                $book = $record->book ?? Book::find($currentBookId);

                if ($book) {
                    $this->applyAvailabilityDelta($book, $delta);
                }
            }

            return;
        }

        if ($originalImpact !== 0) {
            $originalBook = Book::find($originalBookId);

            if ($originalBook) {
                $this->applyAvailabilityDelta($originalBook, -$originalImpact);
            }
        }

        if ($newImpact !== 0) {
            $newBook = $record->book ?? Book::find($currentBookId);

            if ($newBook) {
                $this->applyAvailabilityDelta($newBook, $newImpact);
            }
        }
    }

    public function recordDeleted(BorrowRecord $record): void
    {
        $impact = $this->borrowImpact($record->status);

        if ($impact !== 0) {
            $book = $record->book ?? Book::find($record->book_id);

            if ($book) {
                $this->applyAvailabilityDelta($book, -$impact);
            }
        }
    }

    public function recordRestored(BorrowRecord $record): void
    {
        $impact = $this->borrowImpact($record->status);

        if ($impact !== 0) {
            $book = $record->book ?? Book::find($record->book_id);

            if ($book) {
                $this->applyAvailabilityDelta($book, $impact);
            }
        }
    }
}
