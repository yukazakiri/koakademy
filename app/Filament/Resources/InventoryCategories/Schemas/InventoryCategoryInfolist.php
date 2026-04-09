<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryCategories\Schemas;

use Exception;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryCategoryInfolist
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->label('Category Name')
                            ->weight('semibold')
                            ->size('lg'),

                        TextEntry::make('slug')
                            ->label('Slug')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),

                        TextEntry::make('parent.name')
                            ->label('Parent Category')
                            ->icon('heroicon-o-folder')
                            ->placeholder('No parent category (top-level)'),

                        TextEntry::make('sort_order')
                            ->label('Sort Order')
                            ->badge()
                            ->color('info'),
                    ]),

                Section::make('Status & Statistics')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),

                        TextEntry::make('products_count')
                            ->label('Products')
                            ->getStateUsing(fn ($record) => $record->products()->count())
                            ->icon('heroicon-o-cube')
                            ->suffix(' products'),

                        TextEntry::make('children_count')
                            ->label('Subcategories')
                            ->getStateUsing(fn ($record) => $record->children()->count())
                            ->icon('heroicon-o-folder-open')
                            ->suffix(' subcategories'),
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
