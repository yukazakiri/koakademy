<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\LibrarySystem\Models\BorrowRecord;

final class BorrowRecordInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('book.title')
                    ->label('Book'),
                TextEntry::make('user.name')
                    ->label('User'),
                TextEntry::make('borrowed_at')
                    ->dateTime(),
                TextEntry::make('due_date')
                    ->dateTime(),
                TextEntry::make('returned_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('fine_amount')
                    ->numeric(),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (BorrowRecord $record): bool => $record->trashed()),
            ]);
    }
}
