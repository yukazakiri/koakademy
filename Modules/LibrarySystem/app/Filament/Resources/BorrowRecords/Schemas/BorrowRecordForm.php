<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Filament\Resources\BorrowRecords\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Modules\LibrarySystem\Models\Book;

final class BorrowRecordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Borrow Information')
                    ->schema([
                        Select::make('book_id')
                            ->label('Book')
                            ->relationship('book', 'title')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->getOptionLabelFromRecordUsing(fn (Book $record): string => "{$record->title} - {$record->author->name}"),
                        Select::make('user_id')
                            ->label('Borrower')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'borrowed' => 'Borrowed',
                                'returned' => 'Returned',
                                'lost' => 'Lost',
                            ])
                            ->default('borrowed')
                            ->required()
                            ->reactive(),
                    ])
                    ->columns(3),

                Section::make('Dates')
                    ->schema([
                        DateTimePicker::make('borrowed_at')
                            ->default(now())
                            ->required(),
                        DateTimePicker::make('due_date')
                            ->default(now()->addDays(14))
                            ->required(),
                        DateTimePicker::make('returned_at')
                            ->visible(fn (Get $get): bool => $get('status') === 'returned'),
                    ])
                    ->columns(3),

                Section::make('Additional Information')
                    ->schema([
                        TextInput::make('fine_amount')
                            ->numeric()
                            ->prefix('$')
                            ->step(0.01)
                            ->default(0),
                        Textarea::make('notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
