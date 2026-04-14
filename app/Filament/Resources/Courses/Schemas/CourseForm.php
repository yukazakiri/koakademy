<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Schemas;

use App\Models\Course;
use App\Models\Department;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Course')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Program')
                            ->icon(Heroicon::OutlinedRectangleStack)
                            ->schema([
                                Section::make('Program identity')
                                    ->description('Official program code, title, and which department offers it.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Program code')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(Course::class, 'code', ignoreRecord: true)
                                            ->placeholder('e.g., BSIT')
                                            ->helperText('Stored in uppercase. Must be unique.')
                                            ->afterStateUpdated(function (mixed $state, callable $set): void {
                                                if (is_string($state)) {
                                                    $set('code', mb_strtoupper($state));
                                                }
                                            }),
                                        TextInput::make('title')
                                            ->label('Program title')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull()
                                            ->placeholder('e.g., Bachelor of Science in Information Technology'),
                                        Select::make('department_id')
                                            ->label('Department')
                                            ->relationship('department', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->createOptionForm([
                                                TextInput::make('code')
                                                    ->label('Department code')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(Department::class, 'code')
                                                    ->placeholder('e.g., IT')
                                                    ->helperText('Stored in uppercase. Must be unique.')
                                                    ->afterStateUpdated(function (mixed $state, callable $set): void {
                                                        if (is_string($state)) {
                                                            $set('code', mb_strtoupper($state));
                                                        }
                                                    }),
                                                TextInput::make('name')
                                                    ->label('Department name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('e.g., Information Technology'),
                                                Textarea::make('description')
                                                    ->label('Description')
                                                    ->rows(3)
                                                    ->columnSpanFull()
                                                    ->placeholder('Optional department description.'),
                                                Checkbox::make('is_active')
                                                    ->label('Active')
                                                    ->default(true),
                                            ])
                                            ->helperText('Department offering this program. Create a new one inline if needed.'),
                                        Select::make('school_id')
                                            ->label('School')
                                            ->relationship('school', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Defaults from your current campus context when left blank on create.'),
                                        Textarea::make('description')
                                            ->label('Description')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->placeholder('Overview of the program, outcomes, or notes for staff.')
                                            ->helperText('Optional. Shown where program context is displayed.'),
                                    ]),
                            ]),
                        Tab::make('Structure & scheduling')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->schema([
                                Section::make('Units & per-unit rates')
                                    ->description('Total units and lecture/laboratory rates used for assessment.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('units')
                                            ->label('Total program units')
                                            ->numeric()
                                            ->minValue(0)
                                            ->default(0)
                                            ->helperText('Aggregate units for the full program, if tracked here.'),
                                        TextInput::make('lec_per_unit')
                                            ->label('Lecture rate (per unit)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('₱')
                                            ->helperText('Amount charged per unit for lecture hours.'),
                                        TextInput::make('lab_per_unit')
                                            ->label('Laboratory rate (per unit)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('₱')
                                            ->helperText('Amount charged per unit for laboratory hours.'),
                                    ]),
                                Section::make('Cohort defaults')
                                    ->description('Typical year level, semester, and school year labeling.')
                                    ->columns(2)
                                    ->schema([
                                        Select::make('year_level')
                                            ->label('Default year level')
                                            ->required()
                                            ->default(1)
                                            ->options([
                                                1 => '1st year',
                                                2 => '2nd year',
                                                3 => '3rd year',
                                                4 => '4th year',
                                            ])
                                            ->helperText('Default year level for this program.'),
                                        Select::make('semester')
                                            ->label('Default semester')
                                            ->required()
                                            ->default(1)
                                            ->options([
                                                1 => '1st semester',
                                                2 => '2nd semester',
                                                3 => 'Summer',
                                            ])
                                            ->helperText('Default semester for this program.'),
                                        TextInput::make('school_year')
                                            ->label('School year label')
                                            ->maxLength(255)
                                            ->columnSpanFull()
                                            ->placeholder('e.g., 2024 - 2025')
                                            ->helperText('Free-form label for the active school year.'),
                                    ]),
                            ]),
                        Tab::make('Curriculum & fees')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->schema([
                                Section::make('Curriculum version')
                                    ->description('Which curriculum catalog and miscellaneous fee apply.')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('curriculum_year')
                                            ->label('Curriculum year')
                                            ->maxLength(255)
                                            ->placeholder('e.g., 2024 - 2025')
                                            ->helperText('Used with fee rules (e.g., new vs legacy miscellaneous amounts).'),
                                        TextInput::make('miscelaneous')
                                            ->label('Miscellaneous fee')
                                            ->numeric()
                                            ->minValue(0)
                                            ->prefix('₱')
                                            ->helperText('Overrides curriculum-based miscellaneous when set. Stored as the legacy column name used by billing.'),
                                        Textarea::make('remarks')
                                            ->label('Internal remarks')
                                            ->rows(4)
                                            ->columnSpanFull()
                                            ->placeholder('Registrar notes, accreditation notes, or other staff-only context.')
                                            ->helperText('Not shown to students by default.'),
                                    ]),
                                Section::make('Visibility')
                                    ->schema([
                                        Checkbox::make('is_active')
                                            ->label('Program is active')
                                            ->helperText('Inactive programs can be hidden from selection in student-facing flows.')
                                            ->default(true),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
