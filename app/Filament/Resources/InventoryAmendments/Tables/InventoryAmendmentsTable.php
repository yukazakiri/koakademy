<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryAmendments\Tables;

use App\Models\InventoryAmendment;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class InventoryAmendmentsTable
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
                    ->description(fn (InventoryAmendment $record): string => $record->product->sku),

                TextColumn::make('status')
                    ->label('Status')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('recorded_quantity')
                    ->label('Recorded')
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->suffix(fn (InventoryAmendment $record): string => ' '.$record->product->unit),

                TextColumn::make('actual_quantity')
                    ->label('Actual')
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->suffix(fn (InventoryAmendment $record): string => ' '.$record->product->unit),

                TextColumn::make('variance')
                    ->label('Variance')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state
                    )
                    ->suffix(fn (InventoryAmendment $record): string => ' '.$record->product->unit)
                    ->description(function (InventoryAmendment $record): string {
                        if ($record->recorded_quantity === 0) {
                            return 'N/A';
                        }

                        $percentage = ($record->variance / $record->recorded_quantity) * 100;

                        return ($percentage > 0 ? '+' : '').number_format($percentage, 1).'%';
                    }),

                TextColumn::make('amendedBy.name')
                    ->label('Amended By')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approvedBy.name')
                    ->label('Approved By')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Not approved')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('amendment_date')
                    ->label('Amendment Date')
                    ->dateTime()
                    ->sortable()
                    ->description(fn (InventoryAmendment $record): string => $record->amendment_date->diffForHumans()
                    ),

                TextColumn::make('approved_date')
                    ->label('Approved Date')
                    ->date()
                    ->sortable()
                    ->placeholder('Not approved')
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
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->placeholder('All statuses'),

                SelectFilter::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->placeholder('All products'),

                SelectFilter::make('amended_by')
                    ->label('Amended By')
                    ->relationship('amendedBy', 'name')
                    ->searchable()
                    ->placeholder('All staff'),

                SelectFilter::make('approved_by')
                    ->label('Approved By')
                    ->relationship('approvedBy', 'name')
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
                    ->modalHeading('Delete Amendment')
                    ->modalDescription('Are you sure you want to delete this stock amendment?')
                    ->modalSubmitActionLabel('Delete Amendment'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Amendments')
                        ->modalDescription('Are you sure you want to delete the selected amendments?')
                        ->modalSubmitActionLabel('Delete Amendments'),
                ]),
            ])
            ->defaultSort('amendment_date', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s')
            ->deferLoading()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
