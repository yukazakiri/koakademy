<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryProducts\Schemas;

use App\Enums\InventoryItemType;
use Exception;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryProductInfolist
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
                            ->label('Product Name')
                            ->weight('semibold')
                            ->size('lg'),

                        TextEntry::make('sku')
                            ->label('SKU')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('item_type')
                            ->label('Item Type')
                            ->badge()
                            ->color(fn ($record): string => match ($record->item_type instanceof InventoryItemType ? $record->item_type->value : (string) $record->item_type) {
                                InventoryItemType::Tool->value => 'success',
                                InventoryItemType::Router->value => 'warning',
                                InventoryItemType::Nvr->value => 'primary',
                                InventoryItemType::Cctv->value => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state): string => $state instanceof InventoryItemType ? $state->value : (string) $state)
                            ->placeholder('Not specified'),

                        TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->placeholder('No description provided'),

                        TextEntry::make('category.name')
                            ->label('Category')
                            ->icon('heroicon-o-folder')
                            ->badge()
                            ->color('info')
                            ->placeholder('No category'),

                        TextEntry::make('supplier.name')
                            ->label('Supplier')
                            ->icon('heroicon-o-building-storefront')
                            ->badge()
                            ->color('warning')
                            ->placeholder('No supplier'),
                    ]),

                Section::make('Pricing Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cost')
                            ->label('Cost Price')
                            ->money('PHP')
                            ->icon('heroicon-o-banknotes'),

                        TextEntry::make('price')
                            ->label('Selling Price')
                            ->money('PHP')
                            ->icon('heroicon-o-currency-dollar'),
                    ]),

                Section::make('Stock Information')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('stock_quantity')
                            ->label('Current Stock')
                            ->badge()
                            ->color(fn ($record): string => $record->isLowStock() ? 'danger' : 'success')
                            ->suffix(fn ($record): string => ' '.$record->unit)
                            ->icon('heroicon-o-cube'),

                        TextEntry::make('min_stock_level')
                            ->label('Min Level')
                            ->badge()
                            ->color('warning')
                            ->suffix(fn ($record): string => ' '.$record->unit),

                        TextEntry::make('max_stock_level')
                            ->label('Max Level')
                            ->badge()
                            ->color('info')
                            ->suffix(fn ($record): string => ' '.$record->unit)
                            ->placeholder('No limit'),

                        TextEntry::make('unit')
                            ->label('Unit')
                            ->badge()
                            ->columnSpanFull(),
                    ]),

                Section::make('Location Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('location_building')
                            ->label('Building')
                            ->placeholder('Not specified'),

                        TextEntry::make('location_floor')
                            ->label('Floor')
                            ->placeholder('Not specified'),

                        TextEntry::make('location_area')
                            ->label('Area / Landmark')
                            ->placeholder('Not specified'),
                    ])
                    ->visible(fn ($record): bool => $record->isNetworkDevice()),

                Section::make('Network Configuration')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('IP Address')
                            ->copyable()
                            ->placeholder('Not specified'),

                        TextEntry::make('wifi_ssid')
                            ->label('WiFi SSID')
                            ->copyable()
                            ->placeholder('Not specified'),

                        TextEntry::make('wifi_password')
                            ->label('WiFi Password')
                            ->copyable()
                            ->placeholder('Not specified')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record): bool => $record->isNetworkDevice()),

                Section::make('Access Credentials')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('login_username')
                            ->label('Login Username')
                            ->copyable()
                            ->placeholder('Not specified'),

                        TextEntry::make('login_password')
                            ->label('Login Password')
                            ->copyable()
                            ->placeholder('Not specified'),
                    ])
                    ->visible(fn ($record): bool => $record->isNetworkDevice()),

                Section::make('Additional Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('barcode')
                            ->label('Barcode')
                            ->icon('heroicon-o-qr-code')
                            ->placeholder('No barcode')
                            ->columnSpanFull(),

                        ImageEntry::make('images')
                            ->label('Product Images')
                            ->placeholder('No images')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status & Settings')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('is_active')
                            ->label('Status')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),

                        TextEntry::make('track_stock')
                            ->label('Stock Tracking')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Enabled' : 'Disabled'),

                        TextEntry::make('is_borrowable')
                            ->label('Borrowable')
                            ->badge()
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),

                        TextEntry::make('stock_movements_count')
                            ->label('Stock Movements')
                            ->getStateUsing(fn ($record) => $record->stockMovements()->count())
                            ->icon('heroicon-o-arrows-right-left')
                            ->suffix(' movements'),
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
