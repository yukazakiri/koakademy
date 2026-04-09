<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements\Schemas;

use Exception;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryStockMovementForm
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
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->placeholder('Select a product')
                            ->helperText('Select the product for this stock movement'),

                        Select::make('type')
                            ->label('Movement Type')
                            ->required()
                            ->options([
                                'in' => 'Stock In',
                                'out' => 'Stock Out',
                                'adjustment' => 'Adjustment',
                                'return' => 'Return',
                                'transfer' => 'Transfer',
                            ])
                            ->placeholder('Select movement type')
                            ->helperText('Type of stock movement'),

                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Quantity moved'),

                        DateTimePicker::make('movement_date')
                            ->label('Movement Date')
                            ->required()
                            ->default(now())
                            ->helperText('Date and time of the movement'),
                    ]),

                Section::make('Stock Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('previous_stock')
                            ->label('Previous Stock')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Stock level before movement'),

                        TextInput::make('new_stock')
                            ->label('New Stock')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Stock level after movement'),
                    ]),

                Section::make('Additional Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reference')
                            ->label('Reference Number')
                            ->maxLength(255)
                            ->placeholder('e.g., PO-001, INV-123')
                            ->helperText('Optional reference number (e.g., PO, invoice)'),

                        Select::make('user_id')
                            ->label('User')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->placeholder('Select user')
                            ->helperText('User who made this movement'),

                        Textarea::make('reason')
                            ->label('Reason')
                            ->rows(3)
                            ->placeholder('Reason for this stock movement')
                            ->helperText('Optional reason or notes')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
