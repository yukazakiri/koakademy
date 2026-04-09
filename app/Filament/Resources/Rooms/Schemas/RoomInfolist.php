<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
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
                                    ->label('Room Name'),

                                TextEntry::make('class_code')
                                    ->label('Class Code')
                                    ->placeholder('No class code assigned'),
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
