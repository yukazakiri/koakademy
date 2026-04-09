<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements\Tables;

use App\Models\InventoryStockMovement;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InventoryStockMovementsTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (InventoryStockMovement $record): ?string => $record->product?->sku),

                TextColumn::make('type')
                    ->label('Type')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'in' => 'success',
                        'out' => 'danger',
                        'adjustment' => 'warning',
                        'return' => 'info',
                        'transfer' => 'primary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'in' => 'Stock In',
                        'out' => 'Stock Out',
                        'adjustment' => 'Adjustment',
                        'return' => 'Return',
                        'transfer' => 'Transfer',
                        default => $state,
                    }),

                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->badge()
                    ->color(fn (InventoryStockMovement $record): string => match ($record->type) {
                        'in', 'return' => 'success',
                        'out' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (InventoryStockMovement $record): string => ($record->type === 'in' || $record->type === 'return' ? '+' : '-').$record->quantity
                    ),

                TextColumn::make('previous_stock')
                    ->label('Previous')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('new_stock')
                    ->label('New Stock')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->placeholder('No reference')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No user')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('movement_date')
                    ->label('Movement Date')
                    ->dateTime()
                    ->sortable()
                    ->description(fn (InventoryStockMovement $record): string => $record->movement_date->diffForHumans()
                    ),

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
                SelectFilter::make('type')
                    ->label('Movement Type')
                    ->options([
                        'in' => 'Stock In',
                        'out' => 'Stock Out',
                        'adjustment' => 'Adjustment',
                        'return' => 'Return',
                        'transfer' => 'Transfer',
                    ])
                    ->placeholder('All types'),

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->placeholder('All products'),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->placeholder('All users'),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Stock Movement')
                    ->modalDescription('Are you sure you want to delete this stock movement record?')
                    ->modalSubmitActionLabel('Delete Movement'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Movements')
                        ->modalDescription('Are you sure you want to delete the selected stock movements?')
                        ->modalSubmitActionLabel('Delete Movements'),
                ]),
            ])
            ->defaultSort('movement_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s')
            ->deferLoading()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
