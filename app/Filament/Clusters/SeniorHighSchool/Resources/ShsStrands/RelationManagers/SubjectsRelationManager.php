<?php

declare(strict_types=1);

namespace App\Filament\Clusters\SeniorHighSchool\Resources\ShsStrands\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

final class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    protected static ?string $recordTitleAttribute = 'title';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subject Information')
                    ->description('Basic subject details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Subject Code (Optional)')
                                ->helperText('Leave empty for auto-generated code, or enter custom code')
                                ->maxLength(50),
                            TextInput::make('title')
                                ->label('Subject Title')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description')
                                ->maxLength(500)
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Academic Details')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('grade_year')
                                ->label('Grade Level')
                                ->options([
                                    'Grade 11' => 'Grade 11',
                                    'Grade 12' => 'Grade 12',
                                ])
                                ->required(),
                            Select::make('semester')
                                ->options([
                                    '1st Semester' => '1st Semester',
                                    '2nd Semester' => '2nd Semester',
                                ])
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subject Information')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('code')
                                ->label('Subject Code'),
                            TextEntry::make('title')
                                ->label('Subject Title'),
                            TextEntry::make('description')
                                ->placeholder('-')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Academic Details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('grade_year')
                                ->label('Grade Level'),
                            TextEntry::make('semester'),
                            TextEntry::make('strand.strand_name')
                                ->label('Strand'),
                            TextEntry::make('strand.track.track_name')
                                ->label('Track'),
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('grade_year')
                    ->label('Grade Level')
                    ->sortable(),
                TextColumn::make('semester')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('grade_year')
                    ->label('Grade Level')
                    ->options([
                        'Grade 11' => 'Grade 11',
                        'Grade 12' => 'Grade 12',
                    ]),
                SelectFilter::make('semester')
                    ->options([
                        '1st Semester' => '1st Semester',
                        '2nd Semester' => '2nd Semester',
                    ]),
            ])
            ->headerActions([
                CreateAction::make(),
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
            ])
            ->defaultSort('code');
    }
}
