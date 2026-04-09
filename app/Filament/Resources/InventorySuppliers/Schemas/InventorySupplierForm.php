<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers\Schemas;

use Exception;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventorySupplierForm
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
                            ->label('Supplier Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., ABC Office Supplies Inc.')
                            ->helperText('The name of the supplier company'),

                        TextInput::make('contact_person')
                            ->label('Contact Person')
                            ->maxLength(255)
                            ->placeholder('e.g., John Doe')
                            ->helperText('Primary contact person at the supplier'),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('e.g., contact@supplier.com')
                            ->helperText('Primary email for communication'),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255)
                            ->placeholder('e.g., +63 2 123-4567')
                            ->helperText('Primary contact phone number'),
                    ]),

                Section::make('Address Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('address')
                            ->label('Street Address')
                            ->maxLength(255)
                            ->placeholder('e.g., 123 Main Street')
                            ->columnSpanFull(),

                        TextInput::make('city')
                            ->label('City')
                            ->maxLength(255)
                            ->placeholder('e.g., Manila'),

                        TextInput::make('state')
                            ->label('State/Province')
                            ->maxLength(255)
                            ->placeholder('e.g., Metro Manila'),

                        TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(255)
                            ->placeholder('e.g., 1000'),

                        TextInput::make('country')
                            ->label('Country')
                            ->maxLength(255)
                            ->placeholder('e.g., Philippines')
                            ->default('Philippines'),
                    ]),

                Section::make('Business Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('tax_number')
                            ->label('Tax Number / TIN')
                            ->maxLength(255)
                            ->placeholder('e.g., 123-456-789-000')
                            ->helperText('Tax identification number'),

                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Additional notes about the supplier')
                            ->helperText('Optional notes or special instructions')
                            ->columnSpanFull(),
                    ]),

                Section::make('Settings')
                    ->columns(1)
                    ->schema([
                        Checkbox::make('is_active')
                            ->label('Active Supplier')
                            ->helperText('Inactive suppliers are hidden from product selection')
                            ->default(true),
                    ]),
            ]);
    }
}
