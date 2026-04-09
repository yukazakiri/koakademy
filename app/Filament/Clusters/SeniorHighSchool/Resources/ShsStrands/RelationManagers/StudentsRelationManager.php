<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\RelationManagers;

use App\Models\ShsStrand;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    protected static ?string $recordTitleAttribute = 'fullname';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Basic student identification details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('student_lrn')
                                ->label('LRN')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('fullname')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255),
                            Select::make('gender')
                                ->options([
                                    'Male' => 'Male',
                                    'Female' => 'Female',
                                ])
                                ->required(),
                            DatePicker::make('birthdate')
                                ->required(),
                            TextInput::make('civil_status')
                                ->maxLength(255),
                            TextInput::make('religion')
                                ->maxLength(255),
                            TextInput::make('nationality')
                                ->maxLength(255),
                        ]),
                    ]),
                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('email')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('student_contact')
                                ->label('Contact Number')
                                ->maxLength(255),
                            Textarea::make('complete_address')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Guardian Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('guardian_name')
                                ->maxLength(255),
                            TextInput::make('guardian_contact')
                                ->maxLength(255),
                        ]),
                    ]),
                Section::make('Academic Information')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('grade_level')
                                ->options([
                                    'Grade 11' => 'Grade 11',
                                    'Grade 12' => 'Grade 12',
                                ])
                                ->required(),
                            Textarea::make('remarks')
                                ->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('student_lrn')
                                ->label('LRN'),
                            TextEntry::make('fullname')
                                ->label('Full Name'),
                            TextEntry::make('gender'),
                            TextEntry::make('birthdate')
                                ->date(),
                            TextEntry::make('civil_status'),
                            TextEntry::make('religion'),
                            TextEntry::make('nationality'),
                        ]),
                    ]),
                Section::make('Contact Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('email'),
                            TextEntry::make('student_contact')
                                ->label('Contact Number'),
                            TextEntry::make('complete_address')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Guardian Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('guardian_name'),
                            TextEntry::make('guardian_contact'),
                        ]),
                    ]),
                Section::make('Academic Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('track.track_name')
                                ->label('Track'),
                            TextEntry::make('strand.strand_name')
                                ->label('Strand'),
                            TextEntry::make('grade_level'),
                            TextEntry::make('remarks')
                                ->placeholder('-'),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var ShsStrand $ownerRecord */
        $ownerRecord = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('fullname')
            ->columns([
                TextColumn::make('student_lrn')
                    ->label('LRN')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('fullname')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gender')
                    ->sortable(),
                TextColumn::make('grade_level')
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('student_contact')
                    ->label('Contact')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('grade_level')
                    ->options([
                        'Grade 11' => 'Grade 11',
                        'Grade 12' => 'Grade 12',
                    ]),
                SelectFilter::make('gender')
                    ->options([
                        'Male' => 'Male',
                        'Female' => 'Female',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data) use ($ownerRecord): array {
                        $data['track_id'] = $ownerRecord->track_id;

                        return $data;
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
