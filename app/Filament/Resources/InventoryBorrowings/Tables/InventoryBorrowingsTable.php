<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryBorrowings\Tables;

use App\Models\InventoryBorrowing;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InventoryBorrowingsTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('borrower_name')
                    ->label('Borrower')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (InventoryBorrowing $record): ?string => $record->department),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->description(fn (InventoryBorrowing $record): string => $record->quantity_borrowed.' '.$record->product->unit
                    ),

                TextColumn::make('status')
                    ->label('Status')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'borrowed' => 'warning',
                        'returned' => 'success',
                        'overdue' => 'danger',
                        'lost' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('borrowed_date')
                    ->label('Borrowed')
                    ->dateTime()
                    ->sortable()
                    ->description(fn (InventoryBorrowing $record): string => $record->borrowed_date->diffForHumans()
                    ),

                TextColumn::make('expected_return_date')
                    ->label('Expected Return')
                    ->date()
                    ->sortable()
                    ->placeholder('No date')
                    ->color(fn ($record): string => $record->isOverdue() ? 'danger' : 'gray')
                    ->weight(fn ($record): string => $record->isOverdue() ? 'bold' : 'normal'),

                TextColumn::make('actual_return_date')
                    ->label('Actual Return')
                    ->date()
                    ->sortable()
                    ->placeholder('Not returned')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('quantity_returned')
                    ->label('Returned')
                    ->badge()
                    ->color(fn (InventoryBorrowing $record): string => $record->quantity_returned >= $record->quantity_borrowed ? 'success' : 'warning'
                    )
                    ->formatStateUsing(fn (InventoryBorrowing $record): string => $record->quantity_returned.' / '.$record->quantity_borrowed
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('issuedBy.name')
                    ->label('Issued By')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('borrower_email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('No email')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('borrower_phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('No phone')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'borrowed' => 'Borrowed',
                        'returned' => 'Returned',
                        'overdue' => 'Overdue',
                        'lost' => 'Lost',
                    ])
                    ->placeholder('All statuses'),

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->placeholder('All products'),

                SelectFilter::make('issued_by')
                    ->label('Issued By')
                    ->relationship('issuedBy', 'name')
                    ->searchable()
                    ->placeholder('All staff'),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Borrowing')
                    ->modalDescription('Are you sure you want to delete this borrowing record?')
                    ->modalSubmitActionLabel('Delete Borrowing'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Borrowings')
                        ->modalDescription('Are you sure you want to delete the selected borrowings?')
                        ->modalSubmitActionLabel('Delete Borrowings'),
                ]),
            ])
            ->defaultSort('borrowed_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s')
            ->deferLoading()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
