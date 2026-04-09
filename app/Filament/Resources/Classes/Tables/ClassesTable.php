<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Tables;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\Subject;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class ClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('classification')
                    ->label('Type')
                    ->colors([
                        'primary' => 'college',
                        'success' => 'shs',
                    ])
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            'college' => 'College',
                            'shs' => 'SHS',
                            default => 'College',
                        }
                    )
                    ->sortable()
                    ->visible(fn (): bool => request()->query('activeTab', 'all') === 'all'),

                TextColumn::make('subject_code')
                    ->label('Subject(s)')
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->where(function ($q) use ($search): void {
                            $searchPattern = preg_quote($search, '/');

                            // Search in subject_code field (when not null)
                            $q->where(function ($subQ) use ($search, $searchPattern): void {
                                $subQ->whereNotNull('subject_code')
                                    ->where(function ($codeQ) use ($search, $searchPattern): void {
                                        $codeQ->whereRaw('subject_code ~* ?', ["\\y{$searchPattern}\\y"])
                                            ->orWhere('subject_code', 'ilike', "%{$search}%");
                                    });
                            })
                                // Search via Subject relationship (subject_id)
                                ->orWhereHas('Subject', function ($subQuery) use ($search, $searchPattern): void {
                                    $subQuery->where(function ($codeQ) use ($search, $searchPattern): void {
                                        $codeQ->whereRaw('code ~* ?', ["\\y{$searchPattern}\\y"])
                                            ->orWhere('code', 'ilike', "%{$search}%");
                                    })->orWhere('title', 'ilike', "%{$search}%");
                                })
                                // Search via SHS subject relationship
                                ->orWhereHas('ShsSubject', function ($subQuery) use ($search, $searchPattern): void {
                                    $subQuery->where(function ($codeQ) use ($search, $searchPattern): void {
                                        $codeQ->whereRaw('code ~* ?', ["\\y{$searchPattern}\\y"])
                                            ->orWhere('code', 'ilike', "%{$search}%");
                                    })->orWhere('title', 'ilike', "%{$search}%");
                                })
                                // Search via multiple subjects (subject_ids JSON array)
                                ->orWhereRaw('
                                    subject_ids IS NOT NULL
                                    AND EXISTS (
                                        SELECT 1 FROM subject, jsonb_array_elements_text(classes.subject_ids::jsonb) AS subject_id
                                        WHERE subject.id = subject_id::bigint
                                        AND (subject.code ~* ? OR subject.code ILIKE ? OR subject.title ILIKE ?)
                                    )', ["\\y{$searchPattern}\\y", "%{$search}%", "%{$search}%"]);
                        });
                    })
                    ->sortable()
                    ->getStateUsing(function (Classes $classes): string {
                        // For SHS, display subject code directly
                        if ($classes->isShs()) {
                            return $classes->subject_code ?: 'No Subject Code';
                        }

                        // For College, try to get multiple subjects first
                        $subjects = $classes->subjects;
                        if (! $subjects->isEmpty()) {
                            $codes = $subjects->pluck('code')->filter()->unique()->toArray();
                            if (! empty($codes)) {
                                return implode(', ', $codes);
                            }
                        }

                        // Fallback to subject_code field (which should have the first subject's code)
                        if ($classes->subject_code) {
                            return $classes->subject_code;
                        }

                        // Last resort: try single subject relationship
                        if ($classes->Subject) {
                            return $classes->Subject->code;
                        }

                        return 'No Subject Code';
                    })
                    ->description(function (Classes $classes): string {
                        if ($classes->isShs()) {
                            $title = $classes->ShsSubject?->title ?? 'Unknown Subject';

                            return $title.
                                ' • Track/Strand: '.
                                $classes->formatted_track_strand;
                        }

                        // Try to get multiple subject titles
                        $subjects = $classes->subjects;
                        if (! $subjects->isEmpty()) {
                            $titles = $subjects->pluck('title')->filter()->unique()->toArray();
                            $subjectTitles = empty($titles) ? 'Unknown Subject' : implode(', ', $titles);
                        } else {
                            // Fallback to single subject
                            $subjectTitles = $classes->Subject?->title;

                            // If no relationship, try to find subject by code
                            if (! $subjectTitles && $classes->subject_code) {
                                $subject = Subject::query()->where('code', $classes->subject_code)->first();
                                $subjectTitles = $subject?->title;
                            }

                            $subjectTitles = $subjectTitles ?: 'Unknown Subject';
                        }

                        return $subjectTitles.
                            ' • Courses: '.
                            $classes->formatted_course_codes;
                    }),

                // College-specific columns
                TextColumn::make('formatted_course_codes')
                    ->label('Courses')
                    ->visible(fn (): bool => in_array(request()->query('activeTab', 'all'), ['all', 'college']))
                    ->toggleable(isToggledHiddenByDefault: request()->query('activeTab', 'all') === 'all'),

                // SHS-specific columns
                TextColumn::make('ShsTrack.track_name')
                    ->label('Track')
                    ->visible(fn (): bool => in_array(request()->query('activeTab', 'all'), ['all', 'shs']))
                    ->toggleable(isToggledHiddenByDefault: request()->query('activeTab', 'all') === 'all'),

                TextColumn::make('ShsStrand.strand_name')
                    ->label('Strand')
                    ->visible(fn (): bool => in_array(request()->query('activeTab', 'all'), ['all', 'shs']))
                    ->toggleable(isToggledHiddenByDefault: request()->query('activeTab', 'all') === 'all'),

                TextColumn::make('faculty_name')
                    ->label('Faculty')
                    ->getStateUsing(fn (Classes $classes): string => $classes->Faculty?->full_name ?? 'No faculty assigned')
                    ->sortable(query: fn (Builder $builder, string $direction): Builder => $builder->leftJoin('faculty', 'classes.faculty_id', '=', 'faculty.id')
                        ->orderByRaw("CONCAT(faculty.last_name, ', ', faculty.first_name) ".$direction))
                    ->toggleable()
                    ->placeholder('No faculty assigned')
                    ->description(fn (Classes $classes): ?string => $classes->Faculty?->departmentBelongsTo ? 'Dept: '.$classes->Faculty->departmentBelongsTo->name : null
                    ),

                TextColumn::make('section')
                    ->label('Section')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('semester')
                    ->colors([
                        'primary' => '1st',
                        'secondary' => '2nd',
                        'warning' => 'summer',
                    ])
                    ->formatStateUsing(
                        fn (string $state): string => match ($state) {
                            '1st' => '1st Sem',
                            '2nd' => '2nd Sem',
                            'summer' => 'Summer',
                            default => $state,
                        }
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('school_year')->searchable()->sortable(),

                TextColumn::make('student_count')
                    ->label('Enrolled')
                    ->counts('class_enrollments')
                    ->sortable()
                    ->formatStateUsing(
                        fn (
                            Model $model
                        ): string => sprintf('%s / %s', $model->class_enrollments_count, $model->maximum_slots)
                    ),

                TextColumn::make('maximum_slots')
                    ->label('Max Slots')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                SelectFilter::make('classification')
                    ->label('Class Type')
                    ->options([
                        'college' => 'College',
                        'shs' => 'Senior High School',
                    ])
                    ->placeholder('All Types'),

                SelectFilter::make('course')
                    ->label('Course (College)')
                    ->options(
                        fn () => Course::all()
                            ->pluck('code', 'id')
                            ->sortByDesc('code')
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('Filter by course')
                    ->query(function (Builder $builder, array $data): void {
                        $courseId = $data['value'] ?? null;

                        if (empty($courseId)) {
                            return;
                        }

                        $builder->where('classification', 'college')
                            ->whereRaw('(
                                EXISTS (
                                    SELECT 1 FROM subject
                                    WHERE subject.id = classes.subject_id
                                    AND subject.course_id = ?
                                )
                                OR (
                                    subject_ids IS NOT NULL
                                    AND EXISTS (
                                        SELECT 1 FROM subject, jsonb_array_elements_text(classes.subject_ids::jsonb) AS subject_id
                                        WHERE subject.id = subject_id::bigint
                                        AND subject.course_id = ?
                                    )
                                )
                            )', [$courseId, $courseId]);
                    }),

                SelectFilter::make('shs_track_id')
                    ->label('SHS Track')
                    ->options(fn () => ShsTrack::all()->pluck('track_name', 'id'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Filter by track')
                    ->query(function (Builder $builder, array $data): void {
                        $trackId = $data['value'] ?? null;

                        if (empty($trackId)) {
                            return;
                        }

                        $builder->where('classification', 'shs')
                            ->where('shs_track_id', $trackId);
                    }),

                SelectFilter::make('shs_strand_id')
                    ->label('SHS Strand')
                    ->options(fn () => ShsStrand::all()->pluck('strand_name', 'id'))
                    ->searchable()
                    ->preload()
                    ->placeholder('Filter by strand')
                    ->query(function (Builder $builder, array $data): void {
                        $strandId = $data['value'] ?? null;

                        if (empty($strandId)) {
                            return;
                        }

                        $builder->where('classification', 'shs')
                            ->where('shs_strand_id', $strandId);
                    }),

                SelectFilter::make('subject_code')
                    ->label('Subject')
                    ->options(
                        fn (): array => Subject::query()
                            ->when(
                                request()->input('filters.course'),
                                fn ($query, $courseId) => $query->where(
                                    'course_id',
                                    $courseId
                                )
                            )
                            ->pluck('title', 'code')
                            ->toArray()
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('room')
                    ->label('Room')
                    ->options(fn () => Room::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $builder, array $data): void {
                        $roomId = $data['value'] ?? null;

                        if (empty($roomId)) {
                            return;
                        }

                        $builder->whereRaw('(
                            room_id = ?
                            OR EXISTS (
                                SELECT 1 FROM schedule
                                WHERE schedule.class_id = classes.id
                                AND schedule.room_id = ?
                                AND schedule.deleted_at IS NULL
                            )
                        )', [$roomId, $roomId]);
                    }),

                SelectFilter::make('faculty_id')
                    ->label('Faculty')
                    ->options(
                        fn () => Faculty::all()->mapWithKeys(
                            fn ($faculty): array => [
                                $faculty->id => $faculty->full_name,
                            ]
                        )
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('academic_year')
                    ->options([
                        '1' => '1st Year (College)',
                        '2' => '2nd Year (College)',
                        '3' => '3rd Year (College)',
                        '4' => '4th Year (College)',
                    ])
                    ->label('College Year Level')
                    ->indicator('College Year')
                    ->query(function (Builder $builder, array $data): void {
                        $year = $data['value'] ?? null;

                        if (empty($year)) {
                            return;
                        }

                        $builder->where('classification', 'college')
                            ->where('academic_year', $year);
                    }),

                SelectFilter::make('grade_level')
                    ->options([
                        'Grade 11' => 'Grade 11',
                        'Grade 12' => 'Grade 12',
                    ])
                    ->label('SHS Grade Level')
                    ->indicator('SHS Grade')
                    ->query(function (Builder $builder, array $data): void {
                        $gradeLevel = $data['value'] ?? null;

                        if (empty($gradeLevel)) {
                            return;
                        }

                        $builder->where('classification', 'shs')
                            ->where('grade_level', $gradeLevel);
                    }),

                SelectFilter::make('semester')
                    ->options([
                        '1st' => '1st Semester',
                        '2nd' => '2nd Semester',
                        'summer' => 'Summer',
                    ])
                    ->label('Semester')
                    ->indicator('Semester'),

                Filter::make('available_slots')
                    ->label('Has Available Slots')
                    ->indicator('Available Slots')
                    ->query(
                        fn (Builder $builder): Builder => $builder->whereColumn(
                            'maximum_slots',
                            '>',
                            'class_enrollments_count'
                        )
                    ),

                TernaryFilter::make('fully_enrolled')
                    ->label('Fully Enrolled')
                    ->indicator('Fully Enrolled')
                    ->queries(
                        true: fn (Builder $builder) => $builder->whereColumn(
                            'maximum_slots',
                            '<=',
                            'class_enrollments_count'
                        ),
                        false: fn (Builder $builder) => $builder->whereColumn(
                            'maximum_slots',
                            '>',
                            'class_enrollments_count'
                        )
                    ),

            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('copyClass')
                    ->label('Copy Class')
                    ->icon('heroicon-o-document-duplicate')
                    ->schema([
                        Select::make('section')
                            ->label('New Section')
                            ->options([
                                'A' => 'Section A',
                                'B' => 'Section B',
                                'C' => 'Section C',
                                'D' => 'Section D',
                            ])
                            ->required()
                            ->placeholder('Select section for the copy...'),
                    ])
                    ->action(function (array $data, Classes $classes): void {
                        // Duplicate the class except for schedules and section
                        $newClass = $classes->replicate(['section']);
                        $newClass->section = $data['section'];
                        // Unset any *_count attributes that are not real columns
                        foreach ($newClass->getAttributes() as $key => $value) {
                            if (str_ends_with($key, '_count')) {
                                unset($newClass->{$key});
                            }
                        }

                        $newClass->push();
                        // Do not copy schedules
                    })
                    ->color('primary')
                    ->modalHeading('Copy Class')
                    ->modalDescription(
                        'This will create a new class with the same data except for schedules. Assign a new section.'
                    ),
            ])
            ->toolbarActions([
                Action::make('createClass')
                    ->label('Create Class')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->modalHeading('Create New Class')
                    ->modalDescription('Fill in the details to create a new class')
                    ->form([
                        Group::make()->schema([
                            Grid::make(2)->schema([
                                Select::make('classification')
                                    ->label('Class Type')
                                    ->options([
                                        'college' => 'College',
                                        'shs' => 'Senior High School (SHS)',
                                    ])
                                    ->required()
                                    ->default('college')
                                    ->live()
                                    ->columnSpanFull(),

                                // College Fields
                                Select::make('course_codes')
                                    ->label('Associated Courses')
                                    ->multiple()
                                    ->options(function () {
                                        $enrollmentCourses = app(\App\Services\GeneralSettingsService::class)
                                            ->getGlobalSettingsModel()
                                            ->enrollment_courses ?? [];

                                        if (empty($enrollmentCourses)) {
                                            return [];
                                        }

                                        return Course::query()
                                            ->whereIn('id', $enrollmentCourses)
                                            ->get()
                                            ->mapWithKeys(function ($course): array {
                                                $label = $course->code;
                                                if ($course->curriculum_year) {
                                                    $label .= ' ('.$course->curriculum_year.')';
                                                }

                                                return [$course->id => $label];
                                            });
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn ($get): bool => $get('classification') === 'college')
                                    ->required(fn ($get): bool => $get('classification') === 'college')
                                    ->afterStateUpdated(fn ($set) => $set('subject_ids', null))
                                    ->columnSpanFull(),

                                Select::make('subject_ids')
                                    ->label('Subjects')
                                    ->multiple()
                                    ->options(function ($get) {
                                        $selectedCourses = $get('course_codes');

                                        if (empty($selectedCourses)) {
                                            return [];
                                        }

                                        return Subject::with('course')
                                            ->whereIn('course_id', $selectedCourses)
                                            ->orderBy('code')
                                            ->get()
                                            ->mapWithKeys(function ($subject): array {
                                                $courseCode = $subject->course
                                                    ? $subject->course->code
                                                    : 'No Course';
                                                $display = sprintf('%s - %s (%s)', $subject->code, $subject->title, $courseCode);

                                                return [$subject->id => $display];
                                            });
                                    })
                                    ->searchable()
                                    ->required(fn ($get): bool => $get('classification') === 'college')
                                    ->preload()
                                    ->live()
                                    ->visible(fn ($get): bool => $get('classification') === 'college')
                                    ->disabled(fn ($get): bool => empty($get('course_codes')))
                                    ->columnSpanFull(),

                                // SHS Fields
                                Select::make('shs_track_id')
                                    ->label('SHS Track')
                                    ->options(fn () => ShsTrack::all()->pluck('track_name', 'id'))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn ($get): bool => $get('classification') === 'shs')
                                    ->required(fn ($get): bool => $get('classification') === 'shs'),

                                Select::make('shs_strand_id')
                                    ->label('SHS Strand')
                                    ->options(function ($get) {
                                        $trackId = $get('shs_track_id');
                                        if (! $trackId) {
                                            return [];
                                        }

                                        return ShsStrand::query()
                                            ->where('track_id', $trackId)
                                            ->pluck('strand_name', 'id');
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn ($get): bool => $get('classification') === 'shs')
                                    ->required(fn ($get): bool => $get('classification') === 'shs')
                                    ->disabled(fn ($get): bool => ! $get('shs_track_id')),

                                Select::make('subject_code')
                                    ->label('SHS Subject')
                                    ->options(function ($get) {
                                        $strandId = $get('shs_strand_id');
                                        if (! $strandId) {
                                            return [];
                                        }

                                        return \App\Models\StrandSubject::query()
                                            ->where('strand_id', $strandId)
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
                                    ->required(fn ($get): bool => $get('classification') === 'shs')
                                    ->preload()
                                    ->visible(fn ($get): bool => $get('classification') === 'shs')
                                    ->disabled(fn ($get): bool => ! $get('shs_strand_id'))
                                    ->columnSpanFull(),

                                // Common Fields
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
                                    ->required(),

                                TextInput::make('academic_year')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(4)
                                    ->label('Year Level')
                                    ->visible(fn ($get): bool => $get('classification') === 'college')
                                    ->default(1),

                                Select::make('grade_level')
                                    ->label('Grade Level')
                                    ->options([
                                        'Grade 11' => 'Grade 11 (Junior High School)',
                                        'Grade 12' => 'Grade 12 (Senior High School)',
                                    ])
                                    ->required()
                                    ->visible(fn ($get): bool => $get('classification') === 'shs')
                                    ->default('Grade 11'),

                                Select::make('semester')
                                    ->options([
                                        '1' => '1st Semester',
                                        '2' => '2nd Semester',
                                        'summer' => 'Summer',
                                    ])
                                    ->required()
                                    ->default(app(\App\Services\GeneralSettingsService::class)->getCurrentSemester()),

                                TextInput::make('school_year')
                                    ->required()
                                    ->default(app(\App\Services\GeneralSettingsService::class)->getCurrentSchoolYearString()),

                                Select::make('section')
                                    ->label('Section')
                                    ->options([
                                        'A' => 'Section A',
                                        'B' => 'Section B',
                                        'C' => 'Section C',
                                        'D' => 'Section D',
                                    ])
                                    ->required(),

                                Select::make('room_id')
                                    ->label('Room')
                                    ->relationship(name: 'Room', titleAttribute: 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload(),

                                TextInput::make('maximum_slots')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(40),
                            ]),

                            Section::make('Schedule')
                                ->description('Manage class schedule. Add multiple schedules if needed.')
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

                                                TimePicker::make('start_time')
                                                    ->required()
                                                    ->seconds(false)
                                                    ->label('Start Time'),

                                                TimePicker::make('end_time')
                                                    ->required()
                                                    ->seconds(false)
                                                    ->label('End Time'),

                                                Select::make('room_id')
                                                    ->label('Class Room')
                                                    ->relationship(
                                                        name: 'Room',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn ($query) => $query->where('is_active', true)
                                                    )
                                                    ->required()
                                                    ->searchable()
                                                    ->preload(),
                                            ]),
                                        ])
                                        ->defaultItems(1)
                                        ->reorderable(true)
                                        ->collapsible(),
                                ]),

                            Section::make('Class Settings')
                                ->description('Customize the appearance and features of your class')
                                ->schema([
                                    Grid::make(3)->schema([
                                        ColorPicker::make('settings.background_color')
                                            ->label('Background Color')
                                            ->default('#ffffff'),

                                        ColorPicker::make('settings.accent_color')
                                            ->label('Accent Color')
                                            ->default('#3b82f6'),

                                        Select::make('settings.theme')
                                            ->label('Class Theme')
                                            ->options([
                                                'default' => 'Default',
                                                'modern' => 'Modern',
                                                'classic' => 'Classic',
                                                'minimal' => 'Minimal',
                                                'vibrant' => 'Vibrant',
                                            ])
                                            ->default('default'),
                                    ]),

                                    Grid::make(2)->schema([
                                        Toggle::make('settings.enable_announcements')
                                            ->label('Enable Announcements')
                                            ->default(true),

                                        Toggle::make('settings.enable_grade_visibility')
                                            ->label('Show Grades to Students')
                                            ->default(true),

                                        Toggle::make('settings.enable_attendance_tracking')
                                            ->label('Track Attendance')
                                            ->default(false),

                                        Toggle::make('settings.allow_late_submissions')
                                            ->label('Allow Late Submissions')
                                            ->default(false),

                                        Toggle::make('settings.enable_discussion_board')
                                            ->label('Enable Discussion Board')
                                            ->default(false),
                                    ]),

                                    KeyValue::make('settings.custom')
                                        ->label('Additional Settings')
                                        ->keyLabel('Setting Name')
                                        ->valueLabel('Value')
                                        ->addable()
                                        ->deletable()
                                        ->reorderable()
                                        ->columnSpanFull(),
                                ])
                                ->collapsible()
                                ->collapsed(),
                        ]),
                    ])
                    ->action(function (array $data): void {
                        // Process subject_ids for College classes
                        if ($data['classification'] === 'college' && ! empty($data['subject_ids'])) {
                            // Get all selected subjects to generate subject_code
                            $subjects = Subject::query()->whereIn('id', $data['subject_ids'])->get();
                            $subjectCodes = $subjects->pluck('code')->filter()->unique()->toArray();
                            $data['subject_code'] = implode(', ', $subjectCodes);
                            $data['subject_id'] = $data['subject_ids'][0] ?? null;
                        }

                        // Create the class
                        $class = Classes::create($data);

                        // Handle schedules
                        if (! empty($data['schedules'])) {
                            foreach ($data['schedules'] as $scheduleData) {
                                $class->schedules()->create($scheduleData);
                            }
                        }

                        Notification::make()
                            ->title('Class created successfully')
                            ->success()
                            ->send();
                    })
                    ->modalWidth('7xl'),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('export_class_enrollments')
                        ->label('Export Class Enrollments')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->action(function (): void {
                            // For now, we'll use a simple notification
                            // In a real implementation, you might want to create a custom export job
                            Notification::make()
                                ->title('Export Feature')
                                ->body('Bulk export of class enrollments is available through individual class export actions. Use the "Export Students" button in each class\'s enrolled students tab.')
                                ->info()
                                ->send();
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Export Class Enrollments')
                        ->modalDescription('This will export all enrolled students from the selected classes to an Excel file.')
                        ->tooltip('Export enrolled students from selected classes'),
                ]),
            ]);
    }
}
