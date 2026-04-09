<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryProducts\Tables;

use App\Enums\InventoryItemType;
use App\Models\InventoryProduct;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class InventoryProductsTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Product Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (InventoryProduct $record): ?string => $record->sku),

                TextColumn::make('item_type')
                    ->label('Type')
                    ->badge()
                    ->sortable()
                    ->color(fn (InventoryProduct $record): string => match ($record->item_type instanceof InventoryItemType ? $record->item_type->value : (string) $record->item_type) {
                        InventoryItemType::Tool->value => 'success',
                        InventoryItemType::Router->value => 'warning',
                        InventoryItemType::Nvr->value => 'primary',
                        InventoryItemType::Cctv->value => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => $state instanceof InventoryItemType ? $state->value : (string) $state),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->placeholder('No category'),

                TextColumn::make('supplier.name')
                    ->label('Supplier')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning')
                    ->placeholder('No supplier')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('location')
                    ->label('Location')
                    ->getStateUsing(fn (InventoryProduct $record): ?string => $record->locationLabel())
                    ->placeholder('No location')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->sortable()
                    ->badge()
                    ->color(fn (InventoryProduct $record): string => $record->isLowStock() ? 'danger' : 'success')
                    ->suffix(fn (InventoryProduct $record): string => ' '.$record->unit)
                    ->description(fn (InventoryProduct $record): string => 'Min: '.$record->min_stock_level.' '.$record->unit
                    ),

                TextColumn::make('cost')
                    ->label('Cost')
                    ->money('PHP')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('price')
                    ->label('Price')
                    ->money('PHP')
                    ->sortable(),

                TextColumn::make('barcode')
                    ->label('Barcode')
                    ->searchable()
                    ->placeholder('No barcode')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->placeholder('No IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('track_stock')
                    ->label('Track')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (bool $state): string => $state ? 'Stock tracked' : 'Not tracked')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_borrowable')
                    ->label('Borrowable')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (bool $state): string => $state ? 'Borrowable' : 'Not borrowable')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (bool $state): string => $state ? 'Active' : 'Inactive'),

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
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All products')
                    ->trueLabel('Active products')
                    ->falseLabel('Inactive products'),

                TernaryFilter::make('track_stock')
                    ->label('Stock Tracking')
                    ->placeholder('All products')
                    ->trueLabel('Tracked products')
                    ->falseLabel('Untracked products'),

                TernaryFilter::make('is_borrowable')
                    ->label('Borrowable')
                    ->placeholder('All items')
                    ->trueLabel('Borrowable only')
                    ->falseLabel('Not borrowable'),

                SelectFilter::make('item_type')
                    ->label('Item Type')
                    ->options(InventoryItemType::class)
                    ->placeholder('All types'),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->placeholder('All categories'),

                SelectFilter::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->placeholder('All suppliers'),

                SelectFilter::make('low_stock')
                    ->label('Stock Level')
                    ->placeholder('All products')
                    ->options([
                        'low' => 'Low stock',
                        'normal' => 'Normal stock',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'low' => $query->lowStock(),
                        'normal' => $query->whereColumn('stock_quantity', '>', 'min_stock_level'),
                        default => $query,
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Product')
                    ->modalDescription('Are you sure you want to delete this product? This will affect stock movements and related records.')
                    ->modalSubmitActionLabel('Delete Product'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Products')
                        ->modalDescription('Are you sure you want to delete the selected products?')
                        ->modalSubmitActionLabel('Delete Products'),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s')
            ->deferLoading()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
