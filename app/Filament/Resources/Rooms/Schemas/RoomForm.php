<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

final class RoomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Room 101, Laboratory A'),

                                TextInput::make('class_code')
                                    ->label('Class Code')
                                    ->maxLength(255)
                                    ->placeholder('e.g., LAB-A, LEC-101'),

                                Toggle::make('is_active')
                                    ->label('Active')
                                    ->helperText('When disabled, this room will not appear in class assignment options')
                                    ->default(true)
                                    ->inline(false)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
