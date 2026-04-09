<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryCategories\Tables;

use App\Models\InventoryCategory;
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

final class InventoryCategoriesTable
{
    /**
     * @throws Exception
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Category Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (InventoryCategory $record): ?string => $record->description ?
                        (mb_strlen((string) $record->description) > 100 ?
                            mb_substr((string) $record->description, 0, 100).'...' :
                            $record->description) : null
                    ),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('parent.name')
                    ->label('Parent Category')
                    ->searchable()
                    ->sortable()
                    ->placeholder('No parent')
                    ->badge()
                    ->color('info'),

                TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->badge()
                    ->color('success')
                    ->suffix(' items'),

                TextColumn::make('children_count')
                    ->label('Subcategories')
                    ->counts('children')
                    ->badge()
                    ->color('warning')
                    ->suffix(' subs'),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
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
                    ->placeholder('All categories')
                    ->trueLabel('Active categories')
                    ->falseLabel('Inactive categories'),

                SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->relationship('parent', 'name')
                    ->placeholder('All categories'),
            ])
            ->actions([
                ViewAction::make()
                    ->iconButton(),
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Category')
                    ->modalDescription('Are you sure you want to delete this category? This may affect products assigned to this category.')
                    ->modalSubmitActionLabel('Delete Category'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Categories')
                        ->modalDescription('Are you sure you want to delete the selected categories?')
                        ->modalSubmitActionLabel('Delete Categories'),
                ]),
            ])
            ->defaultSort('sort_order')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s')
            ->deferLoading()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistFiltersInSession();
    }
}
