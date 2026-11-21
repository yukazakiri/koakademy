<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class RoomInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Room Name')
                                    ->weight('semibold'),

                                TextEntry::make('class_code')
                                    ->label('Class Code')
                                    ->badge()
                                    ->placeholder('No class code assigned'),

                                TextEntry::make('type')
                                    ->label('Room Type')
                                    ->badge()
                                    ->color('info')
                                    ->formatStateUsing(fn (?string $state): string => RoomForm::roomTypeOptions()[$state ?? 'lecture'] ?? ucfirst((string) $state)),

                                TextEntry::make('capacity')
                                    ->label('Capacity')
                                    ->suffix(' seats')
                                    ->placeholder('40 seats'),

                                TextEntry::make('building')
                                    ->label('Building / Location')
                                    ->placeholder('Not specified'),

                                TextEntry::make('school.name')
                                    ->label('School')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('All Schools / Default'),

                                TextEntry::make('is_active')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                            ]),
                    ]),

                Section::make('Usage Statistics')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('classes_count')
                                    ->label('Active Classes')
                                    ->state(fn ($record) => $record->classes()->count()),

                                TextEntry::make('schedules_count')
                                    ->label('Scheduled Sessions')
                                    ->state(fn ($record) => $record->schedules()->count()),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
}
