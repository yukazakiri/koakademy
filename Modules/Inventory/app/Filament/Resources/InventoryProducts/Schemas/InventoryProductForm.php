<?php

declare(strict_types=1);

namespace Modules\Inventory\Filament\Resources\InventoryProducts\Schemas;

use Exception;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Inventory\Enums\InventoryItemType;

final class InventoryProductForm
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
                        TextInput::make('name')
                            ->label('Product Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., HP Laptop Charger')
                            ->helperText('The name of the product'),

                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->placeholder('e.g., PROD-001')
                            ->helperText('Stock keeping unit - unique identifier'),

                        Select::make('item_type')
                            ->label('Item Type')
                            ->options(InventoryItemType::class)
                            ->required()
                            ->default(InventoryItemType::Tool->value)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('is_borrowable', $state === InventoryItemType::Tool->value);
                            })
                            ->helperText('Choose the item category for tracking'),

                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull()
                            ->rows(3)
                            ->placeholder('Detailed description of the product')
                            ->helperText('Optional description of the product'),

                        Select::make('category_id')
                            ->label('Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->placeholder('Select a category')
                            ->helperText('Product category'),

                        Select::make('supplier_id')
                            ->label('Supplier')
                            ->relationship('supplier', 'name')
                            ->searchable()
                            ->placeholder('Select a supplier')
                            ->helperText('Product supplier'),
                    ]),

                Section::make('Location Details')
                    ->columns(3)
                    ->schema([
                        TextInput::make('location_building')
                            ->label('Building')
                            ->maxLength(255)
                            ->placeholder('e.g., Main Building')
                            ->helperText('Building where the device is installed'),

                        TextInput::make('location_floor')
                            ->label('Floor')
                            ->maxLength(255)
                            ->placeholder('e.g., 2nd Floor')
                            ->helperText('Floor level or wing'),

                        TextInput::make('location_area')
                            ->label('Area / Landmark')
                            ->maxLength(255)
                            ->placeholder('e.g., North hallway, Server room')
                            ->helperText('Specific placement notes'),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('item_type'), InventoryItemType::networkValues(), true)),

                Section::make('Network Configuration')
                    ->columns(2)
                    ->schema([
                        TextInput::make('ip_address')
                            ->label('IP Address')
                            ->maxLength(255)
                            ->placeholder('e.g., 192.168.1.20')
                            ->rules(['nullable', 'ip'])
                            ->helperText('IPv4 or IPv6 address'),

                        TextInput::make('wifi_ssid')
                            ->label('WiFi SSID')
                            ->maxLength(255)
                            ->placeholder('e.g., ORG-Staff')
                            ->helperText('Wireless network name'),

                        TextInput::make('wifi_password')
                            ->label('WiFi Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->placeholder('Enter WiFi password')
                            ->helperText('Stored securely for reference')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('item_type'), InventoryItemType::networkValues(), true)),

                Section::make('Access Credentials')
                    ->columns(2)
                    ->schema([
                        TextInput::make('login_username')
                            ->label('Login Username')
                            ->maxLength(255)
                            ->placeholder('e.g., admin')
                            ->helperText('Device login username'),

                        TextInput::make('login_password')
                            ->label('Login Password')
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->placeholder('Enter device password')
                            ->helperText('Stored securely for reference'),
                    ])
                    ->visible(fn (Get $get): bool => in_array($get('item_type'), InventoryItemType::networkValues(), true)),

                Section::make('Pricing Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('cost')
                            ->label('Cost Price')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('₱')
                            ->helperText('Unit cost of the product'),

                        TextInput::make('price')
                            ->label('Selling Price')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('₱')
                            ->helperText('Unit selling price'),
                    ]),

                Section::make('Stock Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('stock_quantity')
                            ->label('Current Stock Quantity')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Current quantity in stock'),

                        TextInput::make('min_stock_level')
                            ->label('Minimum Stock Level')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Alert when stock falls below this level'),

                        TextInput::make('max_stock_level')
                            ->label('Maximum Stock Level')
                            ->numeric()
                            ->minValue(0)
                            ->helperText('Maximum stock capacity (optional)'),

                        TextInput::make('unit')
                            ->label('Unit of Measurement')
                            ->required()
                            ->default('pcs')
                            ->maxLength(255)
                            ->placeholder('e.g., pcs, kg, box')
                            ->helperText('Unit for measuring stock'),
                    ]),

                Section::make('Additional Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('barcode')
                            ->label('Barcode')
                            ->maxLength(255)
                            ->placeholder('e.g., 123456789012')
                            ->helperText('Product barcode (optional)'),

                        FileUpload::make('images')
                            ->label('Product Images')
                            ->image()
                            ->multiple()
                            ->maxFiles(5)
                            ->directory('inventory/products')
                            ->helperText('Upload up to 5 product images')
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Additional notes about the product')
                            ->helperText('Optional notes or special instructions')
                            ->columnSpanFull(),
                    ]),

                Section::make('Settings')
                    ->columns(2)
                    ->schema([
                        Checkbox::make('track_stock')
                            ->label('Track Stock')
                            ->helperText('Enable stock tracking for this product')
                            ->default(true),

                        Checkbox::make('is_borrowable')
                            ->label('Borrowable Item')
                            ->helperText('Allow staff to borrow this item')
                            ->default(true)
                            ->visible(fn (Get $get): bool => $get('item_type') === InventoryItemType::Tool->value),

                        Checkbox::make('is_active')
                            ->label('Active Product')
                            ->helperText('Inactive products are hidden from selection')
                            ->default(true),
                    ]),
            ]);
    }
}
