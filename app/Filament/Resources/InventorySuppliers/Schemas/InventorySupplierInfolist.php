<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventorySuppliers\Schemas;

use Exception;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventorySupplierInfolist
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
                            ->label('Supplier Name')
                            ->weight('semibold')
                            ->size('lg'),

                        TextEntry::make('contact_person')
                            ->label('Contact Person')
                            ->icon('heroicon-o-user')
                            ->placeholder('No contact person'),

                        TextEntry::make('email')
                            ->label('Email Address')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->placeholder('No email provided'),

                        TextEntry::make('phone')
                            ->label('Phone Number')
                            ->icon('heroicon-o-phone')
                            ->copyable()
                            ->placeholder('No phone number'),
                    ]),

                Section::make('Address Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('address')
                            ->label('Street Address')
                            ->icon('heroicon-o-map-pin')
                            ->placeholder('No address')
                            ->columnSpanFull(),

                        TextEntry::make('city')
                            ->label('City')
                            ->placeholder('No city'),

                        TextEntry::make('state')
                            ->label('State/Province')
                            ->placeholder('No state'),

                        TextEntry::make('postal_code')
                            ->label('Postal Code')
                            ->placeholder('No postal code'),

                        TextEntry::make('country')
                            ->label('Country')
                            ->placeholder('No country'),
                    ]),

                Section::make('Business Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('tax_number')
                            ->label('Tax Number / TIN')
                            ->icon('heroicon-o-document-text')
                            ->placeholder('No tax number')
                            ->columnSpanFull(),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status & Statistics')
                    ->columns(2)
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
