<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings\Schemas;

use Exception;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryBorrowingInfolist
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
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->weight('semibold')
                            ->size('lg')
                            ->icon('heroicon-o-cube'),

                        TextEntry::make('quantity_borrowed')
                            ->label('Quantity Borrowed')
                            ->badge()
                            ->color('primary')
                            ->suffix(fn ($record): string => ' '.$record->product->unit),
                    ]),

                Section::make('Borrower Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('borrower_name')
                            ->label('Borrower Name')
                            ->icon('heroicon-o-user')
                            ->weight('semibold'),

                        TextEntry::make('borrower_email')
                            ->label('Borrower Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->placeholder('No email'),

                        TextEntry::make('borrower_phone')
                            ->label('Borrower Phone')
                            ->icon('heroicon-o-phone')
                            ->copyable()
                            ->placeholder('No phone'),

                        TextEntry::make('department')
                            ->label('Department')
                            ->icon('heroicon-o-building-office')
                            ->placeholder('No department'),
                    ]),

                Section::make('Borrowing Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'borrowed' => 'warning',
                                'returned' => 'success',
                                'overdue' => 'danger',
                                'lost' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                        TextEntry::make('borrowed_date')
                            ->label('Borrowed Date')
                            ->dateTime()
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('expected_return_date')
                            ->label('Expected Return')
                            ->dateTime()
                            ->icon('heroicon-o-calendar')
                            ->placeholder('No expected date')
                            ->color(fn ($record): string => $record->isOverdue() ? 'danger' : 'gray'),

                        TextEntry::make('purpose')
                            ->label('Purpose')
                            ->placeholder('No purpose provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Return Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('actual_return_date')
                            ->label('Actual Return Date')
                            ->dateTime()
                            ->icon('heroicon-o-calendar-days')
                            ->placeholder('Not yet returned'),

                        TextEntry::make('quantity_returned')
                            ->label('Quantity Returned')
                            ->badge()
                            ->color(fn ($record): string => $record->quantity_returned >= $record->quantity_borrowed ? 'success' : 'warning'
                            )
                            ->suffix(fn ($record): string => ' / '.$record->quantity_borrowed.' '.$record->product->unit),

                        TextEntry::make('return_notes')
                            ->label('Return Notes')
                            ->placeholder('No return notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Staff Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('issuedBy.name')
                            ->label('Issued By')
                            ->icon('heroicon-o-user')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('returnedTo.name')
                            ->label('Returned To')
                            ->icon('heroicon-o-user')
                            ->badge()
                            ->color('success')
                            ->placeholder('Not yet returned'),
                    ]),

                Section::make('Timestamps')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime()
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime()
                            ->icon('heroicon-o-clock')
                            ->since(),
                    ])
                    ->collapsed(),
            ]);
    }
}
