<?php
declare(strict_types=1);

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Enums\SubjectEnrolledEnum;
use App\Models\Subject;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
// use Filament\Forms\Components\Tabs;
// use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Subject Form (Similar to SubjectResource, but within the context of a Course)
                Tabs::make('Subject Details')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Basic Information')
                            ->schema([
                                Section::make('Subject Information')
                                    ->description('Enter the core details for the subject.')
                                    ->schema([
                                        TextInput::make('code')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Subject Code')
                                            ->helperText('Unique code for the subject (e.g., IT101).'),
                                        TextInput::make('title')
                                            ->required()
                                            ->maxLength(255)
                                            ->label('Subject Title')
                                            ->helperText('Full title of the subject.'),
                                        Textarea::make('description')
                                            ->maxLength(255)
                                            ->label('Description')
                                            ->helperText('Brief description of the subject.')
                                            ->columnSpanFull(),
                                        Select::make('classification')
                                            ->required()
                                            ->options(SubjectEnrolledEnum::class)
                                            ->label('Classification')
                                            ->helperText('Classification of the subject.'),
                                        TextInput::make('units')
                                            ->required()
                                            ->numeric()
                                            ->label('Units')
                                            ->helperText('Number of units for the subject.'),
                                        TextInput::make('lecture')
                                            ->numeric()
                                            ->label('Lecture Hours')
                                            ->helperText('Number of lecture hours.'),
                                        TextInput::make('laboratory')
                                            ->numeric()
                                            ->label('Laboratory Hours')
                                            ->helperText('Number of laboratory hours.'),
                                        Select::make('pre_riquisite')
                                            ->label('Pre-requisite')
                                            ->multiple()
                                            ->searchable()
                                            ->getSearchResultsUsing(function (string $search) {
                                                // Get subjects from the same course
                                                // We can't exclude the current subject during search as we don't have access to the record
                                                $currentCourseId = $this->getOwnerRecord()->id;

                                                return Subject::query()
                                                    ->where('course_id', $currentCourseId)
                                                    ->where(function ($query) use ($search): void {
                                                        $query->where('title', 'like', "%{$search}%")
                                                            ->orWhere('code', 'like', "%{$search}%");
                                                    })
                                                    ->limit(50)
                                                    ->get()
                                                    ->mapWithKeys(fn ($subject): array => [$subject->id => "{$subject->code} - {$subject->title}"]);
                                            })
                                            ->getOptionLabelUsing(function ($value) {
                                                $subject = Subject::find($value);

                                                return $subject ? "{$subject->code} - {$subject->title}" : null;
                                            })
                                            ->helperText('Select prerequisite subjects for this subject.')
                                            ->required(false)
                                            ->rules([
                                                fn (): Closure => function (string $attribute, $value, $fail): void {
                                                    if (! is_array($value) || $value === []) {
                                                        return; // No validation needed for empty values
                                                    }

                                                    $currentSubjectId = $this->record?->id;
                                                    if (! $currentSubjectId) {
                                                        return; // Skip validation for new records
                                                    }

                                                    // Check if any of the selected prerequisites already has this subject as a prerequisite
                                                    foreach ($value as $prerequisiteId) {
                                                        $prerequisite = Subject::find($prerequisiteId);
                                                        if ($prerequisite && $prerequisite->pre_riquisite) {
                                                            $prerequisites = is_array($prerequisite->pre_riquisite)
                                                                ? $prerequisite->pre_riquisite
                                                                : json_decode((string) $prerequisite->pre_riquisite, true);

                                                            if (in_array($currentSubjectId, $prerequisites)) {
                                                                $fail("Circular dependency detected: {$prerequisite->title} already has this subject as a prerequisite.");
                                                            }
                                                        }
                                                    }
                                                },
                                            ]),
                                        Checkbox::make('is_credited')
                                            ->label('Is Credited')
                                            ->helperText('Check if this subject is credited.'),
                                    ])->columns(2),
                            ]),
                        Tab::make('Scheduling')
                            ->schema([
                                Section::make('Academic Details')
                                    ->description('Specify academic year, semester, and grouping.')
                                    ->schema([
                                        Select::make('academic_year')
                                            ->options([
                                                1 => '1st Year',
                                                2 => '2nd Year',
                                                3 => '3rd Year',
                                                4 => '4th Year',
                                            ])
                                            ->label('Academic Year')
                                            ->helperText('Academic year for the subject.'),
                                        Select::make('semester')
                                            ->options([
                                                1 => '1st Semester',
                                                2 => '2nd Semester',
                                                3 => 'Summer',
                                            ])
                                            ->label('Semester')
                                            ->helperText('Semester for the subject.'),
                                        TextInput::make('group')
                                            ->maxLength(255)
                                            ->label('Group')
                                            ->helperText('Group, if applicable.'),
                                    ])->columns(3),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->defaultSort('academic_year')
            ->striped()
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('units')
                    ->label('Units')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('academic_year')
                    ->label('Year')
                    ->sortable()
                    ->formatStateUsing(fn (?int $state): string => match ($state) {
                        1 => '1st year',
                        2 => '2nd year',
                        3 => '3rd year',
                        4 => '4th year',
                        default => $state !== null ? (string) $state : '—',
                    })
                    ->sortable(),
                TextColumn::make('semester')
                    ->label('Semester')
                    ->formatStateUsing(fn (?int $state): string => match ($state) {
                        1 => '1st sem.',
                        2 => '2nd sem.',
                        3 => 'Summer',
                        default => $state !== null ? (string) $state : '—',
                    })
                    ->sortable(),
                IconColumn::make('is_credited')
                    ->label('Credited')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                    ->falseIcon(Heroicon::OutlinedXCircle)
                    ->alignCenter(),
                TextColumn::make('pre_riquisite')
                    ->label('Prerequisites')
                    ->formatStateUsing(function ($state) {
                        if (empty($state)) {
                            return 'None';
                        }

                        if (! is_array($state)) {
                            $state = json_decode((string) $state, true);
                        }

                        if (empty($state) || ! is_array($state)) {
                            return 'None';
                        }

                        $prerequisites = Subject::whereIn('id', $state)
                            ->get()
                            ->map(fn ($subject): string => "{$subject->code} — {$subject->title}");

                        return $prerequisites->implode(', ');
                    })
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('academic_year')
                    ->label('Year level')
                    ->options([
                        1 => '1st year',
                        2 => '2nd year',
                        3 => '3rd year',
                        4 => '4th year',
                    ]),
                SelectFilter::make('semester')
                    ->label('Semester')
                    ->options([
                        1 => '1st semester',
                        2 => '2nd semester',
                        3 => 'Summer',
                    ]),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->icon(Heroicon::PencilSquare),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
