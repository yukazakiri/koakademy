<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStudents\Schemas;

use App\Models\ShsStrand;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

final class ShsStudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Student Information')
                    ->tabs([
                        Tab::make('Personal Details')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Section::make('Basic Information')
                                    ->description('Student identification details')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('student_lrn')
                                                ->label('LRN (Learner Reference Number)')
                                                ->required()
                                                ->maxLength(12)
                                                ->placeholder('e.g., 123456789012'),
                                            TextInput::make('fullname')
                                                ->label('Full Name')
                                                ->required()
                                                ->maxLength(255)
                                                ->placeholder('Last Name, First Name Middle Name'),
                                            Select::make('gender')
                                                ->options([
                                                    'Male' => 'Male',
                                                    'Female' => 'Female',
                                                ])
                                                ->required(),
                                            DatePicker::make('birthdate')
                                                ->required()
                                                ->maxDate(now()->subYears(14)),
                                            TextInput::make('civil_status')
                                                ->maxLength(50)
                                                ->default('Single'),
                                            TextInput::make('religion')
                                                ->maxLength(100),
                                            TextInput::make('nationality')
                                                ->maxLength(100)
                                                ->default('Filipino'),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('Contact Information')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Student Contact')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('email')
                                                ->email()
                                                ->maxLength(255)
                                                ->placeholder('student@email.com'),
                                            TextInput::make('student_contact')
                                                ->label('Contact Number')
                                                ->tel()
                                                ->maxLength(20)
                                                ->placeholder('+63 9XX XXX XXXX'),
                                            Textarea::make('complete_address')
                                                ->label('Complete Address')
                                                ->maxLength(500)
                                                ->rows(3)
                                                ->columnSpanFull(),
                                        ]),
                                    ]),
                                Section::make('Guardian Information')
                                    ->description('Parent/Guardian contact details')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('guardian_name')
                                                ->label('Guardian Name')
                                                ->maxLength(255),
                                            TextInput::make('guardian_contact')
                                                ->label('Guardian Contact Number')
                                                ->tel()
                                                ->maxLength(20),
                                        ]),
                                    ]),
                            ]),
                        Tab::make('Academic Information')
                            ->icon('heroicon-o-academic-cap')
                            ->schema([
                                Section::make('Track & Strand')
                                    ->description('Select the student\'s track and strand')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Select::make('track_id')
                                                ->label('Track')
                                                ->relationship('track', 'track_name')
                                                ->required()
                                                ->preload()
                                                ->searchable()
                                                ->live()
                                                ->afterStateUpdated(function (Set $set): void {
                                                    $set('strand_id', null);
                                                }),
                                            Select::make('strand_id')
                                                ->label('Strand')
                                                ->options(function (Get $get): array {
                                                    $trackId = $get('track_id');
                                                    if (! $trackId) {
                                                        return [];
                                                    }

                                                    return ShsStrand::query()
                                                        ->where('track_id', $trackId)
                                                        ->pluck('strand_name', 'id')
                                                        ->toArray();
                                                })
                                                ->preload()
                                                ->searchable()
                                                ->helperText('Select a track first to see available strands'),
                                            Select::make('grade_level')
                                                ->label('Grade Level')
                                                ->options([
                                                    'Grade 11' => 'Grade 11',
                                                    'Grade 12' => 'Grade 12',
                                                ])
                                                ->required(),
                                        ]),
                                    ]),
                                Section::make('Additional Notes')
                                    ->schema([
                                        Textarea::make('remarks')
                                            ->maxLength(500)
                                            ->rows(4)
                                            ->placeholder('Any additional notes about the student...')
                                            ->columnSpanFull(),
                                    ])
                                    ->collapsed(),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->persistTabInQueryString(),
            ]);
    }
}
