<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

final class ShsStrandInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Strand Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('strand_name')
                                ->label('Strand Name')
                                ->weight('bold')
                                ->size('lg'),
                            TextEntry::make('track.track_name')
                                ->label('Track')
                                ->badge()
                                ->color('primary'),
                            TextEntry::make('description')
                                ->placeholder('No description provided')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Statistics')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('students_count')
                                ->label('Total Students')
                                ->state(fn ($record): int => $record->students()->count())
                                ->badge()
                                ->color('success'),
                            TextEntry::make('subjects_count')
                                ->label('Total Subjects')
                                ->state(fn ($record): int => $record->subjects()->count())
                                ->badge()
                                ->color('info'),
                            TextEntry::make('track.strands_count')
                                ->label('Strands in Track')
                                ->state(fn ($record): int => $record->track?->strands()->count() ?? 0)
                                ->badge()
                                ->color('warning'),
                        ]),
                    ]),
                Section::make('Timestamps')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('created_at')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->dateTime(),
                        ]),
                    ])
                    ->collapsed(),
            ]);
    }
}
