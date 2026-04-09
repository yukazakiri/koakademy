<?php

declare(strict_types=1);

namespace App\Filament\Resources\InventoryAmendments\Schemas;

use Exception;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class InventoryAmendmentInfolist
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
                        TextEntry::make('product.name')
                            ->label('Product')
                            ->weight('semibold')
                            ->size('lg')
                            ->icon('heroicon-o-cube'),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                    ]),

                Section::make('Stock Details')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('recorded_quantity')
                            ->label('Recorded')
                            ->badge()
                            ->color('gray')
                            ->suffix(fn ($record): string => ' '.$record->product->unit)
                            ->icon('heroicon-o-document-text'),

                        TextEntry::make('actual_quantity')
                            ->label('Actual')
                            ->badge()
                            ->color('info')
                            ->suffix(fn ($record): string => ' '.$record->product->unit)
                            ->icon('heroicon-o-clipboard-document-check'),

                        TextEntry::make('variance')
                            ->label('Variance')
                            ->badge()
                            ->color(fn (int $state): string => match (true) {
                                $state > 0 => 'success',
                                $state < 0 => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state
                            )
                            ->suffix(fn ($record): string => ' '.$record->product->unit)
                            ->icon('heroicon-o-arrows-right-left'),

                        TextEntry::make('variance')
                            ->label('Percentage')
                            ->badge()
                            ->color(fn (int $state): string => match (true) {
                                $state > 0 => 'success',
                                $state < 0 => 'danger',
                                default => 'gray',
                            })
                            ->formatStateUsing(function ($record): string {
                                if ($record->recorded_quantity === 0) {
                                    return 'N/A';
                                }

                                $percentage = ($record->variance / $record->recorded_quantity) * 100;

                                return ($percentage > 0 ? '+' : '').number_format($percentage, 1).'%';
                            }),
                    ]),

                Section::make('Amendment Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('amendment_date')
                            ->label('Amendment Date')
                            ->dateTime()
                            ->icon('heroicon-o-calendar-days'),

                        TextEntry::make('notes')
                            ->label('Notes')
                            ->placeholder('No notes provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Staff Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('amendedBy.name')
                            ->label('Amended By')
                            ->icon('heroicon-o-user')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('approvedBy.name')
                            ->label('Approved By')
                            ->icon('heroicon-o-user')
                            ->badge()
                            ->color('success')
                            ->placeholder('Not yet approved'),
                    ]),

                Section::make('Approval Information')
                    ->columns(1)
                    ->schema([
                        TextEntry::make('approved_date')
                            ->label('Approved Date')
                            ->dateTime()
                            ->icon('heroicon-o-calendar-days')
                            ->placeholder('Not yet approved'),
                    ])
                    ->visible(fn ($record): bool => $record->approved_date !== null),

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
