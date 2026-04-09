<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ShsStrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Strand Information')
                    ->description('Basic strand details and track association')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('strand_name')
                                ->label('Strand Name')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g., STEM, ABM, HUMSS'),
                            Select::make('track_id')
                                ->label('Track')
                                ->relationship('track', 'track_name')
                                ->required()
                                ->preload()
                                ->searchable()
                                ->createOptionForm([
                                    TextInput::make('track_name')
                                        ->label('Track Name')
                                        ->required()
                                        ->maxLength(255),
                                    Textarea::make('description')
                                        ->maxLength(500),
                                ]),
                            Textarea::make('description')
                                ->maxLength(500)
                                ->rows(3)
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
