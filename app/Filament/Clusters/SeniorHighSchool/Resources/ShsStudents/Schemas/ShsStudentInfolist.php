<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

final class ShsStudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Student Details')
                    ->tabs([
                        Tab::make('Personal Information')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Basic Details')
                                    ->schema([
                                        Grid::make(4)->schema([
                                            TextEntry::make('student_lrn')
                                                ->label('LRN')
                                                ->copyable()
                                                ->weight('bold'),
                                            TextEntry::make('fullname')
                                                ->label('Full Name')
                                                ->weight('bold')
                                                ->size('lg'),
                                            TextEntry::make('gender')
                                                ->badge()
                                                ->color(fn (string $state): string => match ($state) {
                                                    'Male' => 'info',
                                                    'Female' => 'pink',
                                                    default => 'gray',
                                                }),
                                            TextEntry::make('birthdate')
                                                ->date(),
                                            TextEntry::make('civil_status')
                                                ->placeholder('-'),
                                            TextEntry::make('religion')
                                                ->placeholder('-'),
                                            TextEntry::make('nationality')
                                                ->placeholder('-'),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('Contact Information')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Student Contact')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('email')
                                                ->icon('heroicon-o-envelope')
                                                ->copyable()
                                                ->placeholder('-'),
                                            TextEntry::make('student_contact')
                                                ->label('Contact Number')
                                                ->icon('heroicon-o-phone')
                                                ->copyable()
                                                ->placeholder('-'),
                                            TextEntry::make('complete_address')
                                                ->label('Complete Address')
                                                ->icon('heroicon-o-map-pin')
                                                ->columnSpanFull()
                                                ->placeholder('-'),
                                        ]),
                                    ]),
                                Section::make('Guardian Information')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextEntry::make('guardian_name')
                                                ->label('Guardian Name')
                                                ->icon('heroicon-o-user')
                                                ->placeholder('-'),
                                            TextEntry::make('guardian_contact')
                                                ->label('Guardian Contact')
                                                ->icon('heroicon-o-phone')
                                                ->copyable()
                                                ->placeholder('-'),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('Academic Information')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Section::make('Track & Strand')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('track.track_name')
                                                ->label('Track')
                                                ->badge()
                                                ->color('primary'),
                                            TextEntry::make('strand.strand_name')
                                                ->label('Strand')
                                                ->badge()
                                                ->color('success'),
                                            TextEntry::make('grade_level')
                                                ->badge()
                                                ->color('warning'),
                                        ]),
                                    ]),
                                Section::make('Strand Subjects')
                                    ->schema([
                                        TextEntry::make('strand.subjects')
                                            ->label('Enrolled Subjects')
                                            ->listWithLineBreaks()
                                            ->bulleted()
                                            ->state(fn ($record): array => $record->strand?->subjects?->pluck('title')->toArray() ?? ['No subjects available'])
                                            ->placeholder('No strand assigned'),
                                    ])
                                    ->collapsed(),
                                Section::make('Additional Notes')
                                    ->schema([
                                        TextEntry::make('remarks')
                                            ->placeholder('No remarks'),
                                    ])
                                    ->collapsed(),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
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
