<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryStockMovements\Schemas;

use Exception;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryStockMovementInfolist
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
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->weight('semibold')
                            ->size('lg')
                            ->icon('heroicon-o-cube'),

                        TextEntry::make('type')
                            ->label('Movement Type')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'in' => 'success',
                                'out' => 'danger',
                                'adjustment' => 'warning',
                                'return' => 'info',
                                'transfer' => 'primary',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'in' => 'Stock In',
                                'out' => 'Stock Out',
                                'adjustment' => 'Adjustment',
                                'return' => 'Return',
                                'transfer' => 'Transfer',
                                default => $state,
                            }),

                        TextEntry::make('quantity')
                            ->label('Quantity')
                            ->badge()
                            ->color('primary')
                            ->icon('heroicon-o-cube'),

                        TextEntry::make('movement_date')
                            ->label('Movement Date')
                            ->dateTime()
                            ->icon('heroicon-o-calendar-days'),
                    ]),

                Section::make('Stock Details')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('previous_stock')
                            ->label('Previous Stock')
                            ->badge()
                            ->color('gray')
                            ->icon('heroicon-o-arrow-left'),

                        TextEntry::make('quantity')
                            ->label('Change')
                            ->badge()
                            ->color(fn ($record): string => match ($record->type) {
                                'in', 'return' => 'success',
                                'out' => 'danger',
                                default => 'warning',
                            })
                            ->formatStateUsing(fn ($record): string => ($record->type === 'in' || $record->type === 'return' ? '+' : '-').$record->quantity
                            )
                            ->icon('heroicon-o-arrows-right-left'),

                        TextEntry::make('new_stock')
                            ->label('New Stock')
                            ->badge()
                            ->color('success')
                            ->icon('heroicon-o-arrow-right'),
                    ]),

                Section::make('Additional Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Reference Number')
                            ->icon('heroicon-o-document-text')
                            ->placeholder('No reference')
                            ->copyable(),

                        TextEntry::make('user.name')
                            ->label('User')
                            ->icon('heroicon-o-user')
                            ->placeholder('No user assigned'),

                        TextEntry::make('reason')
                            ->label('Reason')
                            ->placeholder('No reason provided')
                            ->columnSpanFull(),
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
