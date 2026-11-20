<?php

declare(strict_types=1);

namespace App\Filament\Resources\Rooms\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class RoomForm
{
    /**
     * @return array<string, string>
     */
    public static function roomTypeOptions(): array
    {
        return [
            'lecture' => 'Lecture Room',
            'laboratory' => 'Laboratory',
            'computer_lab' => 'Computer Laboratory',
            'auditorium' => 'Auditorium',
            'conference' => 'Conference Room',
            'gymnasium' => 'Gymnasium',
            'library' => 'Library',
            'other' => 'Other',
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Room Information')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Room Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Room 101, Laboratory A'),

                                TextInput::make('class_code')
                                    ->label('Class Code')
                                    ->maxLength(255)
                                    ->placeholder('e.g., LAB-A, LEC-101'),

                                Select::make('type')
                                    ->label('Room Type')
                                    ->options(self::roomTypeOptions())
                                    ->default('lecture')
                                    ->required(),

                                TextInput::make('capacity')
                                    ->label('Capacity')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(5000)
                                    ->default(40)
                                    ->required()
                                    ->suffix('seats'),

                                TextInput::make('building')
                                    ->label('Building / Location')
                                    ->maxLength(100)
                                    ->placeholder('e.g., Main Building, Annex B'),

                                Select::make('school_id')
                                    ->label('School')
                                    ->relationship('school', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Default / Current School'),

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
