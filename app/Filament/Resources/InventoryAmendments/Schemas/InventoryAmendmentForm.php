<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryAmendments\Schemas;

use Exception;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryAmendmentForm
{
    /**
     * @throws Exception
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Product Information')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable()
                            ->placeholder('Select a product')
                            ->helperText('Product to amend stock for'),

                        Select::make('status')
                            ->label('Status')
                            ->required()
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->helperText('Current status of the amendment'),
                    ]),

                Section::make('Stock Details')
                    ->columns(3)
                    ->schema([
                        TextInput::make('recorded_quantity')
                            ->label('Recorded Quantity')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Quantity in the system'),

                        TextInput::make('actual_quantity')
                            ->label('Actual Quantity')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Actual physical count'),

                        TextInput::make('variance')
                            ->label('Variance')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->helperText('Difference (actual - recorded)')
                            ->disabled()
                            ->dehydrated(),
                    ]),

                Section::make('Amendment Information')
                    ->columns(2)
                    ->schema([
                        DateTimePicker::make('amendment_date')
                            ->label('Amendment Date')
                            ->required()
                            ->default(now())
                            ->helperText('Date of the stock amendment'),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Reason for amendment or additional notes')
                            ->helperText('Optional notes about the amendment')
                            ->columnSpanFull(),
                    ]),

                Section::make('Staff Information')
                    ->columns(2)
                    ->schema([
                        Select::make('amended_by')
                            ->label('Amended By')
                            ->relationship('amendedBy', 'name')
                            ->required()
                            ->searchable()
                            ->placeholder('Select user')
                            ->helperText('Staff member who created the amendment'),

                        Select::make('approved_by')
                            ->label('Approved By')
                            ->relationship('approvedBy', 'name')
                            ->searchable()
                            ->placeholder('Select user')
                            ->helperText('Staff member who approved (if applicable)'),
                    ]),

                Section::make('Approval Information')
                    ->columns(1)
                    ->schema([
                        DateTimePicker::make('approved_date')
                            ->label('Approved Date')
                            ->helperText('Date when the amendment was approved'),
                    ])
                    ->visibleOn('edit'),
            ]);
    }
}
