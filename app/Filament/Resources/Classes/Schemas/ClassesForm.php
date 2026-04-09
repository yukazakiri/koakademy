<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Schemas;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\StrandSubject;
use App\Models\Subject;
use App\Rules\ScheduleOverlapRule;
use App\Services\GeneralSettingsService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

final class ClassesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make()
                    ->schema([

                        Grid::make(2)->schema([
                            // Step 1: Select Class Type (College or SHS)
                            Select::make('classification')
                                ->label('Class Type')
                                ->placeholder('Choose class type...')
                                ->options([
                                    'college' => 'College',
                                    'shs' => 'Senior High School (SHS)',
                                ])
                                ->required()
                                ->default('college')
                                ->live()
                                ->helperText('Select whether this is a College or Senior High School class')
                                ->afterStateUpdated(function (Set $set, $state): void {
                                    // Clear related fields when classification changes
                                    $set('subject_code', null);
                                    $set('subject_ids', null);
                                    $set('course_codes', null);
                                    $set('shs_track_id', null);
                                    $set('shs_strand_id', null);
                                    $set('academic_year', null);
                                    $set('grade_level', null);
                                })
                                ->validationMessages([
                                    'required' => 'Please select a class type to continue.',
                                ])
                                ->columnSpanFull(),

                            // === COLLEGE FIELDS ===

                            // Step 2: Select Courses (for College only)
                            Select::make('course_codes')
                                ->label('Associated Courses')
                                ->placeholder('Select one or more courses...')
                                ->options(function () {
                                    $generalSettingsService = app(
                                        GeneralSettingsService::class
                                    );
                                    $enrollmentCourses = $generalSettingsService->getGlobalSettingsModel()
                                        ->enrollment_courses;
                                    if (empty($enrollmentCourses)) {
                                        return [];
                                    }

                                    return Course::query()->whereIn('id', $enrollmentCourses)
                                        ->get()
                                        ->mapWithKeys(function ($course): array {
                                            $label = $course->code;
                                            if ($course->curriculum_year) {
                                                $label .=
                                                    ' ('.
                                                    $course->curriculum_year.
                                                    ')';
                                            }

                                            return [$course->id => $label];
                                        });
                                })
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->live()
                                ->visible(fn (Get $get): bool => $get('classification') === 'college')
                                ->required(fn (Get $get): bool => $get('classification') === 'college')
                                ->afterStateUpdated(function (Set $set, $state): void {
                                    // Clear subject selection when courses change
                                    $set('subject_ids', null);
                                    $set('subject_id', null);
                                    $set('subject_code', null);
                                })
                                ->helperText(
                                    'Select which courses this class is available for (e.g., BSIT, BSBA, BSHM). Subjects will be filtered based on your selection.'
                                )
                                ->columnSpanFull(),

                            // Step 3: Select Subjects (for College only)
                            Select::make('subject_ids')
                                ->label('Subjects')
                                ->placeholder('Select one or more subjects...')
                                ->multiple()
                                ->options(function (Get $get) {
                                    $selectedCourses = $get('course_codes');

                                    // Require courses to be selected first
                                    if (empty($selectedCourses)) {
                                        return [];
                                    }

                                    // Get subjects that belong to any of the selected courses
                                    return Subject::with('course')
                                        ->whereIn('course_id', $selectedCourses)
                                        ->orderBy('code')
                                        ->get()
                                        ->mapWithKeys(function ($subject): array {
                                            $courseCode = $subject->course
                                                ? $subject->course->code
                                                : 'No Course';
                                            // Show the exact subject code (including any trailing spaces) for clarity
                                            $display = sprintf('%s - %s (%s)', $subject->code, $subject->title, $courseCode);

                                            return [
                                                $subject->id => $display,
                                            ];
                                        });
                                })
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('classification') === 'college')
                                ->preload()
                                ->live()
                                ->visible(fn (Get $get): bool => $get('classification') === 'college')
                                ->disabled(fn (Get $get): bool => empty($get('course_codes')))
                                ->helperText(function (Get $get): string {
                                    if (empty($get('course_codes'))) {
                                        return '⚠️ Please select courses first to see available subjects.';
                                    }

                                    return 'Select one or more subjects from the selected courses. Multiple subjects can be shared across courses.';
                                })
                                ->columnSpanFull()
                                ->createOptionForm([
                                    TextInput::make('code')
                                        ->label('Subject Code')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('e.g., ACCTNG 1, GE-1, ITW 101')
                                        ->helperText('Enter a unique code for the subject'),
                                    TextInput::make('title')
                                        ->label('Subject Title')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g., Fundamentals of Accounting')
                                        ->helperText('Enter the full title of the subject'),
                                    TextInput::make('units')
                                        ->label('Units')
                                        ->numeric()
                                        ->default(3)
                                        ->required()
                                        ->minValue(1)
                                        ->maxValue(6),
                                    TextInput::make('lecture')
                                        ->label('Lecture Hours')
                                        ->numeric()
                                        ->default(3)
                                        ->minValue(0),
                                    TextInput::make('laboratory')
                                        ->label('Laboratory Hours')
                                        ->numeric()
                                        ->default(0)
                                        ->minValue(0),
                                    Select::make('academic_year')
                                        ->label('Academic Year')
                                        ->options([
                                            1 => '1st Year',
                                            2 => '2nd Year',
                                            3 => '3rd Year',
                                            4 => '4th Year',
                                        ])
                                        ->required()
                                        ->default(1),
                                    Select::make('semester')
                                        ->label('Semester')
                                        ->options([
                                            1 => '1st Semester',
                                            2 => '2nd Semester',
                                        ])
                                        ->required()
                                        ->default(1),
                                    Select::make('course_id')
                                        ->label('Course')
                                        ->options(fn () => Course::all()->pluck('code', 'id'))
                                        ->required()
                                        ->searchable()
                                        ->preload(),
                                    Select::make('classification')
                                        ->label('Classification')
                                        ->options([
                                            'credited' => 'Credited',
                                            'non_credited' => 'Non-Credited',
                                            'internal' => 'Internal',
                                        ])
                                        ->default('credited')
                                        ->required(),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $subject = Subject::query()->create($data);

                                    return $subject->id;
                                })
                                ->afterStateUpdated(function (
                                    Set $set,
                                    $state,
                                    Get $get
                                ): void {
                                    // Auto-populate subject_code based on selected subjects
                                    if (! empty($state) && is_array($state)) {
                                        // Get all selected subjects
                                        $subjects = Subject::query()->whereIn('id', $state)->get();

                                        // Generate subject code from all selected subject codes
                                        $subjectCodes = $subjects->pluck('code')->filter()->unique()->toArray();
                                        $generatedCode = implode(', ', $subjectCodes);

                                        // Always update the subject_code to show live changes
                                        $set('subject_code', $generatedCode);

                                        // Set first subject as subject_id for backward compatibility
                                        $firstSubjectId = $state[0];
                                        $set('subject_id', $firstSubjectId);
                                    } else {
                                        $set('subject_code', null);
                                        $set('subject_id', null);
                                    }
                                }),

                            // Subject Code Input (College) - for custom class name
                            TextInput::make('subject_code')
                                ->label('Class Name / Subject Code')
                                ->placeholder('Select subjects to auto-generate...')
                                ->helperText('Auto-generated from selected subjects. You can edit this to create your own custom class name.')
                                ->maxLength(255)
                                ->live()
                                ->visible(fn (Get $get): bool => $get('classification') === 'college' && ! empty($get('subject_ids')))
                                ->dehydrated()
                                ->columnSpanFull(),

                            // === SHS FIELDS ===

                            // Step 2: Select SHS Track (for SHS only)
                            Select::make('shs_track_id')
                                ->label('SHS Track')
                                ->placeholder('Select a track...')
                                ->options(fn () => ShsTrack::all()->pluck('track_name', 'id'))
                                ->searchable()
                                ->preload()
                                ->live()
                                ->visible(fn (Get $get): bool => $get('classification') === 'shs')
                                ->required(fn (Get $get): bool => $get('classification') === 'shs')
                                ->helperText('Choose the SHS track for this class (e.g., Academic Track, TVL Track)')
                                ->createOptionForm([
                                    TextInput::make('track_name')
                                        ->label('Track Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g., Academic Track, TVL Track')
                                        ->helperText('Enter the name of the new SHS track'),
                                    Textarea::make('description')
                                        ->label('Description')
                                        ->maxLength(500)
                                        ->placeholder('Brief description of the track...')
                                        ->helperText('Optional description of the track'),
                                ])
                                ->createOptionUsing(function (array $data) {
                                    $shsTrack = ShsTrack::query()->create($data);

                                    return $shsTrack->getKey();
                                })
                                ->afterStateUpdated(function (Set $set, $state): void {
                                    $set('shs_strand_id', null);
                                    $set('subject_code', null);
                                })
                                ->validationMessages([
                                    'required' => 'Please select an SHS track before proceeding.',
                                ]),

                            // SHS Strand Selection
                            Select::make('shs_strand_id')
                                ->label('SHS Strand')
                                ->placeholder('Select a strand...')
                                ->options(function (Get $get) {
                                    $trackId = $get('shs_track_id');
                                    if (! $trackId) {
                                        return [];
                                    }

                                    return ShsStrand::query()->where('track_id', $trackId)
                                        ->pluck('strand_name', 'id');
                                })
                                ->searchable()
                                ->preload()
                                ->live()
                                ->visible(fn (Get $get): bool => $get('classification') === 'shs')
                                ->required(fn (Get $get): bool => $get('classification') === 'shs')
                                ->disabled(fn (Get $get): bool => ! $get('shs_track_id'))
                                ->helperText(function (Get $get): string {
                                    if (! $get('shs_track_id')) {
                                        return 'Please select a track first to see available strands.';
                                    }

                                    return 'Choose the specific strand within the selected track.';
                                })
                                ->createOptionForm([
                                    TextInput::make('strand_name')
                                        ->label('Strand Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g., STEM, ABM, HUMSS, ICT')
                                        ->helperText('Enter the name of the new strand'),
                                    Textarea::make('description')
                                        ->label('Description')
                                        ->maxLength(500)
                                        ->placeholder('Brief description of the strand...')
                                        ->helperText('Optional description of the strand'),
                                    Hidden::make('track_id')
                                        ->default(fn (Get $get): mixed => $get('../../shs_track_id')),
                                ])
                                ->createOptionUsing(function (array $data, Get $get) {
                                    $data['track_id'] = $get('shs_track_id');
                                    $shsStrand = ShsStrand::query()->create($data);

                                    return $shsStrand->getKey();
                                })
                                ->afterStateUpdated(function (Set $set, $state): void {
                                    $set('subject_code', null);
                                })
                                ->validationMessages([
                                    'required' => 'Please select an SHS strand before proceeding.',
                                ]),

                            // Step 4: Select SHS Subject (for SHS only)
                            Select::make('subject_code')
                                ->label('SHS Subject')
                                ->placeholder('Select a subject...')
                                ->options(function (Get $get) {
                                    $strandId = $get('shs_strand_id');
                                    if (! $strandId) {
                                        return [];
                                    }

                                    return StrandSubject::query()->where('strand_id', $strandId)
                                        ->get()
                                        ->mapWithKeys(function ($subject): array {
                                            $display = sprintf('%s - %s', $subject->code, $subject->title);
                                            if ($subject->grade_year) {
                                                $display .= sprintf(' (%s)', $subject->grade_year);
                                            }

                                            return [$subject->code => $display];
                                        });
                                })
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('classification') === 'shs')
                                ->preload()
                                ->visible(fn (Get $get): bool => $get('classification') === 'shs')
                                ->disabled(fn (Get $get): bool => ! $get('shs_strand_id'))
                                ->helperText(function (Get $get): string {
                                    if (! $get('shs_strand_id')) {
                                        return '⚠️ Please select a track and strand first to see available subjects.';
                                    }

                                    return 'Choose the subject to be taught in this class.';
                                })
                                ->columnSpanFull()
                                ->createOptionForm([
                                    TextInput::make('code')
                                        ->label('Subject Code')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('e.g., STEM-MATH11, ABM-ACCT11')
                                        ->helperText('Enter a unique code for the subject'),
                                    TextInput::make('title')
                                        ->label('Subject Title')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g., General Mathematics, Fundamentals of Accountancy')
                                        ->helperText('Enter the full title of the subject'),
                                    Textarea::make('description')
                                        ->label('Description')
                                        ->maxLength(500)
                                        ->placeholder('Brief description of the subject...')
                                        ->helperText('Optional description of the subject'),
                                    Select::make('grade_year')
                                        ->label('Grade Year')
                                        ->options([
                                            'Grade 11' => 'Grade 11',
                                            'Grade 12' => 'Grade 12',
                                        ])
                                        ->required()
                                        ->default('Grade 11'),
                                    Select::make('semester')
                                        ->label('Semester')
                                        ->options([
                                            '1st' => '1st Semester',
                                            '2nd' => '2nd Semester',
                                        ])
                                        ->required()
                                        ->default('1st'),
                                    Hidden::make('strand_id')
                                        ->default(fn (Get $get): mixed => $get('../../shs_strand_id')),
                                ])
                                ->createOptionUsing(function (array $data, Get $get) {
                                    $data['strand_id'] = $get('shs_strand_id');
                                    $strandSubject = StrandSubject::query()->create($data);

                                    return $strandSubject->code;
                                })
                                ->validationMessages([
                                    'required' => 'Please select a subject for this SHS class.',
                                ]),

                            // === COMMON FIELDS ===

                            Select::make('faculty_id')
                                ->label('Faculty')
                                ->options(
                                    fn () => Faculty::all()->mapWithKeys(
                                        fn ($faculty): array => [
                                            $faculty->id => $faculty->full_name,
                                        ]
                                    )
                                )
                                ->searchable()
                                ->preload()
                                ->placeholder('Select a faculty member...')
                                ->helperText('Choose the faculty member who will teach this class'),

                            // College Academic Year
                            TextInput::make('academic_year')
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(4)
                                ->label('Year Level')
                                ->visible(fn (Get $get): bool => $get('classification') === 'college')
                                ->default(fn (): int => 1),

                            // SHS Grade Level
                            Select::make('grade_level')
                                ->label('Grade Level')
                                ->placeholder('Select grade level...')
                                ->options([
                                    'Grade 11' => 'Grade 11 (Junior High School)',
                                    'Grade 12' => 'Grade 12 (Senior High School)',
                                ])
                                ->required()
                                ->visible(fn (Get $get): bool => $get('classification') === 'shs')
                                ->default('Grade 11')
                                ->helperText('Select the appropriate grade level for this SHS class')
                                ->validationMessages([
                                    'required' => 'Please select a grade level for this SHS class.',
                                ]),

                            Select::make('semester')
                                ->options([
                                    '1' => '1st Semester',
                                    '2' => '2nd Semester',
                                    'summer' => 'Summer',
                                ])
                                ->required()
                                ->default(function () {
                                    $generalSettingsService = app(
                                        GeneralSettingsService::class
                                    );

                                    return $generalSettingsService->getCurrentSemester();
                                }),

                            TextInput::make('school_year')
                                ->required()
                                ->placeholder('e.g. 2023-2024')
                                ->default(function () {
                                    $generalSettingsService = app(
                                        GeneralSettingsService::class
                                    );

                                    return $generalSettingsService->getCurrentSchoolYearString();
                                }),

                            Select::make('section')
                                ->label('Section')
                                ->options([
                                    'A' => 'Section A',
                                    'B' => 'Section B',
                                    'C' => 'Section C',
                                    'D' => 'Section D',
                                ])
                                ->required()
                                ->placeholder('Select section...')
                                ->helperText('Choose the section for this class'),

                            Select::make('room_id')
                                ->label('Room')
                                ->options(
                                    fn () => Room::active()->pluck('name', 'id')
                                )
                                ->searchable()
                                ->preload()
                                ->helperText('Only active rooms are shown')
                                ->createOptionForm([
                                    TextInput::make('name')
                                        ->label('Room Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g., Computer Laboratory 1')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (Set $set, $state): void {
                                            if ($state) {
                                                // Auto-generate slug from name
                                                $slug = Str::slug($state, '-');
                                                $slug = mb_strtoupper($slug);
                                                $set('class_code', $slug);
                                            }
                                        })
                                        ->helperText('Enter the room name (slug will be auto-generated)'),
                                    TextInput::make('class_code')
                                        ->label('Room Code/Slug')
                                        ->required()
                                        ->maxLength(50)
                                        ->placeholder('AUTO-GENERATED')
                                        ->helperText('Auto-generated from room name, but you can modify it'),
                                ])
                                ->createOptionUsing(fn (array $data) => Room::query()->create([
                                    'name' => $data['name'],
                                    'class_code' => $data['class_code'],
                                ])->getKey()),

                            TextInput::make('maximum_slots')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->default(40)
                                ->label('Maximum Slots'),
                        ]),

                        Section::make('Schedule')
                            ->description(
                                'Manage class schedule. Add multiple schedules if needed.'
                            )
                            ->schema([
                                Repeater::make('schedules')
                                    ->relationship()
                                    ->schema([
                                        Grid::make(4)->schema([
                                            Select::make('day_of_week')
                                                ->options([
                                                    'Monday' => 'Monday',
                                                    'Tuesday' => 'Tuesday',
                                                    'Wednesday' => 'Wednesday',
                                                    'Thursday' => 'Thursday',
                                                    'Friday' => 'Friday',
                                                    'Saturday' => 'Saturday',
                                                ])
                                                ->required()
                                                ->label('Day'),

                                            TimePicker::make(
                                                'start_time'
                                            )
                                                ->required()
                                                ->seconds(false)
                                                ->label('Start Time'),

                                            TimePicker::make(
                                                'end_time'
                                            )
                                                ->required()
                                                ->seconds(false)
                                                ->label('End Time')
                                                ->afterStateUpdated(function (
                                                    Set $set,
                                                    $state,
                                                    Get $get
                                                ): void {
                                                    $startTime = $get(
                                                        'start_time'
                                                    );
                                                    if ($startTime && $state && $state < $startTime) {
                                                        $set(
                                                            'end_time',
                                                            $startTime
                                                        );
                                                    }
                                                }),

                                            Select::make('room_id')
                                                ->label('Class Room')
                                                ->relationship(
                                                    name: 'Room',
                                                    titleAttribute: 'name',
                                                    modifyQueryUsing: fn ($query) => $query->where('is_active', true)
                                                )
                                                ->required()
                                                ->searchable()
                                                ->createOptionForm([
                                                    TextInput::make('name')
                                                        ->label('Room Name')
                                                        ->required()
                                                        ->maxLength(255)
                                                        ->placeholder('e.g., Computer Laboratory 1')
                                                        ->live(onBlur: true)
                                                        ->afterStateUpdated(function (Set $set, $state): void {
                                                            if ($state) {
                                                                // Auto-generate slug from name
                                                                $slug = Str::slug($state, '-');
                                                                $slug = mb_strtoupper($slug);
                                                                $set('class_code', $slug);
                                                            }
                                                        })
                                                        ->helperText('Enter the room name (code will be auto-generated)'),
                                                    TextInput::make('class_code')
                                                        ->label('Room Code/Slug')
                                                        ->required()
                                                        ->maxLength(50)
                                                        ->placeholder('AUTO-GENERATED')
                                                        ->helperText('Auto-generated from room name, but you can modify it'),
                                                ])
                                                ->preload(),
                                        ]),
                                    ])
                                    ->defaultItems(1)
                                    ->reorderable(true)
                                    ->collapsible()
                                    ->rules([new ScheduleOverlapRule])
                                    ->validationMessages([
                                        'schedule_overlap' => 'The schedule overlaps with another.',
                                    ])
                                    ->cloneable()
                                    ->itemLabel(
                                        fn (array $state): ?string => $state['day_of_week'] ?? null
                                    ),
                            ]),

                        Section::make('Class Settings')
                            ->description('Customize the appearance and features of your class')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Fieldset::make('Visual Customization')
                                    ->schema([
                                        ColorPicker::make('settings.background_color')
                                            ->label('Background Color')
                                            ->default('#ffffff')
                                            ->helperText('Choose a background color for the class page'),

                                        ColorPicker::make('settings.accent_color')
                                            ->label('Accent Color')
                                            ->default('#3b82f6')
                                            ->helperText('Primary accent color for buttons and highlights'),

                                        Select::make('settings.theme')
                                            ->label('Class Theme')
                                            ->options([
                                                'default' => 'Default',
                                                'modern' => 'Modern',
                                                'classic' => 'Classic',
                                                'minimal' => 'Minimal',
                                                'vibrant' => 'Vibrant',
                                            ])
                                            ->default('default')
                                            ->helperText('Choose a visual theme for your class'),

                                        FileUpload::make('settings.banner_image')
                                            ->label('Class Banner Image')
                                            ->image()
                                            ->maxSize(2048)
                                            ->directory('class-banners')
                                            ->helperText('Upload a banner image (max 2MB)')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(3),

                                Fieldset::make('Feature Settings')
                                    ->schema([
                                        Toggle::make('settings.enable_announcements')
                                            ->label('Enable Announcements')
                                            ->default(true)
                                            ->helperText('Allow posting announcements to students'),

                                        Toggle::make('settings.enable_grade_visibility')
                                            ->label('Show Grades to Students')
                                            ->default(true)
                                            ->helperText('Allow students to view their grades'),

                                        Toggle::make('settings.enable_attendance_tracking')
                                            ->label('Track Attendance')
                                            ->default(false)
                                            ->helperText('Enable attendance tracking for this class'),

                                        Toggle::make('settings.allow_late_submissions')
                                            ->label('Allow Late Submissions')
                                            ->default(false)
                                            ->helperText('Accept submissions after the deadline'),

                                        Toggle::make('settings.enable_discussion_board')
                                            ->label('Enable Discussion Board')
                                            ->default(false)
                                            ->helperText('Allow student discussions in this class'),
                                    ])
                                    ->columns(2),

                                Fieldset::make('Custom Preferences')
                                    ->schema([
                                        KeyValue::make('settings.custom')
                                            ->label('Additional Settings')
                                            ->keyLabel('Setting Name')
                                            ->valueLabel('Value')
                                            ->addable()
                                            ->deletable()
                                            ->reorderable()
                                            ->helperText('Add any custom settings specific to this class')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->collapsible()
                            ->collapsed(),
                    ])
                    ->columnSpan(['lg' => 2]),

                Group::make()
                    ->schema([
                        Section::make('Timestamps')
                            ->schema([
                                Placeholder::make('created_at')
                                    ->label('Created at')
                                    ->content(
                                        fn (
                                            ?Classes $classes
                                        ): string => $classes?->created_at?->diffForHumans() ??
                                            'Never'
                                    ),

                                Placeholder::make('updated_at')
                                    ->label('Last modified at')
                                    ->content(
                                        fn (
                                            ?Classes $classes
                                        ): string => $classes?->updated_at?->diffForHumans() ??
                                            'Never'
                                    ),
                            ])
                            ->hidden(fn (?Classes $classes): bool => ! $classes instanceof Classes),
                    ])
                    ->columnSpan(['lg' => 1]),
            ]);
    }
}
