<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers\Tables;

use App\Models\InventorySupplier;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

final class InventorySuppliersTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Supplier Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (InventorySupplier $record): ?string => $record->contact_person ?
                        'Contact: '.$record->contact_person : null
                    ),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No email')
                    ->copyable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('No phone')
                    ->copyable(),

                TextColumn::make('city')
                    ->label('City')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No city')
                    ->description(fn (InventorySupplier $record): ?string => $record->country),

                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->badge()
                    ->color('success')
                    ->suffix(' items'),

                TextColumn::make('address')
                    ->label('Address')
                    ->searchable()
                    ->placeholder('No address')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('state')
                    ->label('State')
                    ->searchable()
                    ->placeholder('No state')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('postal_code')
                    ->label('Postal Code')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('tax_number')
                    ->label('Tax Number')
                    ->searchable()
                    ->placeholder('No tax number')
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
                    ->placeholder('All suppliers')
                    ->trueLabel('Active suppliers')
                    ->falseLabel('Inactive suppliers'),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Supplier')
                    ->modalDescription('Are you sure you want to delete this supplier? This may affect products associated with this supplier.')
                    ->modalSubmitActionLabel('Delete Supplier'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Suppliers')
                        ->modalDescription('Are you sure you want to delete the selected suppliers?')
                        ->modalSubmitActionLabel('Delete Suppliers'),
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
