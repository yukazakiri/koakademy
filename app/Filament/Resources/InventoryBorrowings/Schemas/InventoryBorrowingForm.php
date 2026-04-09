<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings\Schemas;

use Exception;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryBorrowingForm
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship(
                                name: 'product',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->borrowable()
                            )
                            ->required()
                            ->searchable()
                            ->placeholder('Select a product')
                            ->helperText('Product being borrowed'),

                        TextInput::make('quantity_borrowed')
                            ->label('Quantity Borrowed')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Number of items borrowed'),
                    ]),

                Section::make('Borrower Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('borrower_name')
                            ->label('Borrower Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., John Doe')
                            ->helperText('Name of the person borrowing'),

                        TextInput::make('borrower_email')
                            ->label('Borrower Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('e.g., john@example.com')
                            ->helperText('Email address of borrower'),

                        TextInput::make('borrower_phone')
                            ->label('Borrower Phone')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('e.g., +63 912 345 6789')
                            ->helperText('Phone number of borrower'),

                        TextInput::make('department')
                            ->label('Department')
                            ->maxLength(255)
                            ->placeholder('e.g., IT Department')
                            ->helperText('Borrower\'s department (optional)'),
                    ]),

                Section::make('Borrowing Details')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('borrowed_date')
                            ->label('Borrowed Date')
                            ->required()
                            ->default(now())
                            ->helperText('Date and time borrowed'),

                        DateTimePicker::make('expected_return_date')
                            ->label('Expected Return Date')
                            ->helperText('Expected date of return (optional)'),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'borrowed' => 'Borrowed',
                                'returned' => 'Returned',
                                'overdue' => 'Overdue',
                                'lost' => 'Lost',
                            ])
                            ->default('borrowed')
                            ->helperText('Current status of the borrowing'),

                        Textarea::make('purpose')
                            ->label('Purpose')
                            ->rows(3)
                            ->placeholder('Reason for borrowing')
                            ->helperText('Optional purpose or reason for borrowing')
                            ->columnSpanFull(),
                    ]),

                Section::make('Return Information')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('actual_return_date')
                            ->label('Actual Return Date')
                            ->helperText('Actual date returned (filled when returned)'),

                        TextInput::make('quantity_returned')
                            ->label('Quantity Returned')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Number of items returned'),

                        Textarea::make('return_notes')
                            ->label('Return Notes')
                            ->rows(3)
                            ->placeholder('Notes about the return')
                            ->helperText('Optional notes about the return condition')
                            ->columnSpanFull(),
                    ]),

                Section::make('Staff Information')
                    ->columns(2)
                    ->schema([
                        Select::make('issued_by')
                            ->label('Issued By')
                            ->relationship('issuedBy', 'name')
                            ->required()
                            ->searchable()
                            ->placeholder('Select user')
                            ->helperText('Staff member who issued the item'),

                        Select::make('returned_to')
                            ->label('Returned To')
                            ->relationship('returnedTo', 'name')
                            ->searchable()
                            ->placeholder('Select user')
                            ->helperText('Staff member who received the return'),
                    ]),
            ]);
    }
}
