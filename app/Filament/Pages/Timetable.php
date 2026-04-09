<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\GeneralSettingsService;
use App\Services\PdfGenerationService;
use App\Services\TimetableConflictService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid as ComponentsGrid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

final class Timetable extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public $selectedView = 'room';

    public $selectedId;

    public $selectedYearLevel;

    public $schedules;

    public $currentSchoolYear;

    public $currentSemester;

    // protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Timetable';

    protected static ?string $title = 'Schedule Management';

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 5;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected string $view = 'filament.pages.timetable';

    private array $cachedOptions = [];

    private $conflicts = [];

    private $conflictSummary = [];

    public function mount(): void
    {
        // Get current academic period settings using GeneralSettingsService
        $settingsService = app(GeneralSettingsService::class);
        $this->currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $this->currentSemester = $settingsService->getCurrentSemester();

        // Initialize form with default values
        $this->form->fill([
            'selectedView' => $this->selectedView,
            'selectedId' => $this->selectedId,
            'selectedYearLevel' => $this->selectedYearLevel,
        ]);

        $this->schedules = collect([]);
        $this->cachedOptions = [];
        $this->conflicts = [];
        $this->conflictSummary = [];
    }

    public function updatedSelectedView($value): void
    {
        $this->selectedView = $value;
        $this->selectedId = null;
        $this->selectedYearLevel = null;
        $this->schedules = collect([]);
        $this->cachedOptions = [];
        $this->conflicts = [];
        $this->conflictSummary = [];

        // Reset form state completely
        $this->form->fill([
            'selectedView' => $this->selectedView,
            'selectedId' => null,
            'selectedYearLevel' => null,
        ]);

        // Dispatch browser event to force component refresh
        $this->dispatch('refresh-select-options');

        // Force re-render
        $this->dispatch('$refresh');
    }

    public function updatedSelectedId($value): void
    {
        $this->selectedId = $value;

        // Reset year level when course changes
        if ($this->selectedView === 'course') {
            $this->selectedYearLevel = null;
        }

        // Add a small delay to show loading state
        $this->dispatch('show-loading');

        // Debug logging
        Log::info('Timetable: updatedSelectedId', [
            'view' => $this->selectedView,
            'selectedId' => $this->selectedId,
            'selectedYearLevel' => $this->selectedYearLevel,
            'will_load' => $this->selectedId && ($this->selectedView !== 'course' || $this->selectedYearLevel),
        ]);

        if ($this->selectedId && ($this->selectedView !== 'course' || $this->selectedYearLevel)) {
            $this->loadSchedules();
        } else {
            $this->schedules = collect([]);
            $this->conflicts = [];
            $this->conflictSummary = [];
        }

        // Dispatch event to hide loading
        $this->dispatch('hide-loading');
    }

    public function updatedSelectedYearLevel($value): void
    {
        $this->selectedYearLevel = $value;

        // Add a small delay to show loading state
        $this->dispatch('show-loading');

        // Debug logging
        Log::info('Timetable: updatedSelectedYearLevel', [
            'view' => $this->selectedView,
            'selectedId' => $this->selectedId,
            'selectedYearLevel' => $this->selectedYearLevel,
            'will_load' => $this->selectedId && $this->selectedYearLevel,
        ]);

        if ($this->selectedId && $this->selectedYearLevel) {
            $this->loadSchedules();
        } else {
            $this->schedules = collect([]);
            $this->conflicts = [];
            $this->conflictSummary = [];
        }

        // Dispatch event to hide loading
        $this->dispatch('hide-loading');
    }

    public function loadSchedules(): void
    {
        $query = $this->getTableQuery()
            ->with([
                'class.subject',
                'class.faculty',
                'class.class_enrollments',
                'room',
            ])
            ->orderBy('day_of_week')
            ->orderBy('start_time');

        // Debug logging
        Log::info('Timetable: loadSchedules query', [
            'view' => $this->selectedView,
            'selectedId' => $this->selectedId,
            'selectedYearLevel' => $this->selectedYearLevel,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        $this->schedules = $query->get();

        Log::info('Timetable: loadSchedules result', [
            'count' => $this->schedules->count(),
        ]);

        $this->detectConflicts();
    }

    public function getFormData(): array
    {
        return [
            'selectedView' => $this->selectedView,
            'selectedId' => $this->selectedId,
            'selectedYearLevel' => $this->selectedYearLevel,
        ];
    }

    public function refreshForm(): void
    {
        $this->cachedOptions = [];
        $this->form->fill($this->getFormData());

        // Force Livewire to re-render the component
        $this->dispatch('$refresh');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        // Ensure form data is always current
        $this->form->fill($this->getFormData());

        return parent::render();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('day_of_week')->label('Day')->sortable(),

                TextColumn::make('start_time')
                    ->label('Time')
                    ->formatStateUsing(
                        fn ($record): string => "{$record->start_time->format(
                            'H:i'
                        )} - {$record->end_time->format('H:i')}"
                    )
                    ->sortable(),

                TextColumn::make('class.subject.title')
                    ->label('Subject')
                    ->description(function ($record): string {
                        $descriptions = [];
                        $descriptions[] = "Section {$record->class?->section}";

                        // Add year level for course view
                        if ($this->selectedView === 'course' && $record->class) {
                            $yearLevel = $record->class->academic_year;
                            if ($yearLevel) {
                                $yearLevelText = match ($yearLevel) {
                                    '1' => '1st Year',
                                    '2' => '2nd Year',
                                    '3' => '3rd Year',
                                    '4' => '4th Year',
                                    default => "{$yearLevel} Year"
                                };
                                $descriptions[] = "Year: {$yearLevelText}";
                            }

                            $courseCodes = $record->class->formatted_course_codes;
                            if ($courseCodes && $courseCodes !== 'N/A') {
                                $descriptions[] = "Courses: {$courseCodes}";
                            }
                        }

                        return implode(' | ', $descriptions);
                    })
                    ->searchable(),

                TextColumn::make('room.name')
                    ->label('Room')
                    ->visible(fn (): bool => $this->selectedView !== 'room')
                    ->searchable(),

                TextColumn::make('class.faculty.full_name')
                    ->label('Faculty')
                    ->visible(fn (): bool => $this->selectedView !== 'faculty')
                    ->searchable(),

                TextColumn::make('class.formatted_course_codes')
                    ->label('Course(s)')
                    ->visible(fn (): bool => in_array($this->selectedView, ['room', 'student', 'faculty']))
                    ->searchable(),

                TextColumn::make('class.classification')
                    ->label('Type')
                    ->visible(fn (): bool => in_array($this->selectedView, ['course', 'faculty']))
                    ->formatStateUsing(fn ($state): string => $state ? ucfirst((string) $state) : 'College')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'shs' => 'warning',
                        'college' => 'success',
                        default => 'gray'
                    }),
            ])
            ->actions([
                FilamentAction::make('view_class')
                    ->label('View Class Details')
                    ->icon('heroicon-m-academic-cap')
                    ->url(
                        fn ($record): ?string => $record->class
                            ? route('filament.admin.resources.classes.view', [
                                'record' => $record->class_id,
                            ])
                            : null
                    )
                    ->disabled(fn ($record): bool => ! $record->class_id)
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('day_of_week', 'asc')
            ->defaultSort('start_time', 'asc')
            ->striped()
            ->paginated(false);
    }

    public function getViewData(): array
    {
        // Ensure schedules are loaded if we have a selected ID
        if ($this->selectedId && $this->schedules->isEmpty() && ($this->selectedView !== 'course' || $this->selectedYearLevel)) {
            $this->loadSchedules();
        }

        return [
            'schedules' => $this->schedules,
            'timeSlots' => $this->getTimeSlots(),
            'days' => $this->getDays(),
        ];
    }

    /**
     * Get time slots with better granularity for positioning
     */
    public function getTimeSlotsForPositioning(): array
    {
        $slots = [];
        for ($hour = 7; $hour <= 21; $hour++) {
            $slots[] = sprintf('%02d:00', $hour);
        }

        return $slots;
    }

    /**
     * Calculate schedule position and height based on time
     */
    public function calculateSchedulePosition($schedule): array
    {
        $startTime = \Carbon\Carbon::parse($schedule->start_time);
        $endTime = \Carbon\Carbon::parse($schedule->end_time);

        // Base hour (7 AM = 0)
        $baseHour = 7;
        $slotHeight = 80; // Height of each hour slot in pixels

        // Calculate position from start time
        $startHourOffset = $startTime->hour - $baseHour;
        $startMinuteOffset = $startTime->minute / 60;
        $topPosition = ($startHourOffset + $startMinuteOffset) * $slotHeight;

        // Calculate height from duration
        $durationInHours = $startTime->diffInMinutes($endTime) / 60;
        $height = $durationInHours * $slotHeight;

        // Minimum height for readability
        $height = max($height, 40);

        return [
            'top' => $topPosition,
            'height' => $height,
        ];
    }

    public function getSelectedEntityName(): string
    {
        if (! $this->selectedId) {
            return '';
        }

        return match ($this->selectedView) {
            'room' => Room::find($this->selectedId)?->name ?? 'Unknown Room',
            'class' => Classes::find($this->selectedId)?->subject?->title.
                ' (Section '.
                Classes::find($this->selectedId)?->section.
                ')' ??
                'Unknown Class',
            'student' => Student::find($this->selectedId)?->full_name ??
                'Unknown Student',
            'course' => $this->getCourseEntityName(),
            'faculty' => Faculty::find($this->selectedId)?->full_name ??
                'Unknown Faculty',
            default => '',
        };
    }

    /**
     * Check if a specific schedule has conflicts
     */
    public function scheduleHasConflict($schedule): bool
    {
        if (empty($this->conflicts)) {
            return false;
        }

        foreach ($this->conflicts as $conflicts) {
            foreach ($conflicts as $conflict) {
                if (isset($conflict['conflicts'])) {
                    foreach ($conflict['conflicts'] as $conflictDetail) {
                        if (isset($conflictDetail['schedule1']) &&
                            $conflictDetail['schedule1']['id'] === $schedule->id) {
                            return true;
                        }
                        if (isset($conflictDetail['schedule2']) &&
                            $conflictDetail['schedule2']['id'] === $schedule->id) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get all detected conflicts
     */
    public function getConflicts(): array
    {
        return $this->conflicts;
    }

    /**
     * Check if there are any conflicts
     */
    public function hasConflicts(): bool
    {
        return ! empty($this->conflicts) &&
               array_sum(array_map(count(...), $this->conflicts)) > 0;
    }

    /**
     * Get conflict count
     */
    public function getConflictCount(): int
    {
        if (empty($this->conflicts)) {
            return 0;
        }

        return array_sum(array_map(count(...), $this->conflicts));
    }

    /**
     * Get conflict summary text
     */
    public function getConflictSummaryText(): string
    {
        if (empty($this->conflictSummary)) {
            return 'No conflicts detected.';
        }

        $summary = $this->conflictSummary;
        $parts = [];

        if ($summary['high_severity'] > 0) {
            $parts[] = "{$summary['high_severity']} critical";
        }
        if ($summary['medium_severity'] > 0) {
            $parts[] = "{$summary['medium_severity']} medium";
        }
        if ($summary['low_severity'] > 0) {
            $parts[] = "{$summary['low_severity']} low priority";
        }

        $text = 'Found '.implode(', ', $parts).' conflicts.';

        if ($summary['by_type']['time_room'] > 0) {
            $text .= " Includes {$summary['by_type']['time_room']} room/time conflicts.";
        }
        if ($summary['by_type']['faculty'] > 0) {
            $text .= " Includes {$summary['by_type']['faculty']} faculty conflicts.";
        }

        return $text;
    }

    /**
     * Clear conflict cache
     */
    public function clearConflictCache(): void
    {
        $conflictService = app(TimetableConflictService::class);
        $cacheKey = "timetable_conflicts_{$this->selectedView}_{$this->selectedId}";
        $conflictService->clearConflictCache($cacheKey);

        // Re-detect conflicts
        $this->detectConflicts();
    }

    public function getEmptySlotMessage(): string
    {
        return match ($this->selectedView) {
            'room' => 'Room Available',
            'course', 'year_level' => 'No Class',
            default => 'No Schedule',
        };
    }

    public function getTimetableTitle(): string
    {
        $base = 'Schedule Timetable';

        if ($this->selectedView === 'room' && $this->selectedId) {
            $room = Room::find($this->selectedId);

            return $base.' - Room '.($room->name ?? '');
        }

        if ($this->selectedView === 'course' && $this->selectedId) {
            $course = Course::find($this->selectedId);

            return $base.' - '.$course->code.' ('.$course->title.')';
        }

        if ($this->selectedView === 'year_level' && $this->selectedYearLevel) {
            $suffix = match ($this->selectedYearLevel) {
                '1' => '1st Year',
                '2' => '2nd Year',
                '3' => '3rd Year',
                '4' => '4th Year',
                default => $this->selectedYearLevel.' Year',
            };

            return $base.' - '.$suffix;
        }

        return $base;
    }

    public function getScheduleForDayAndTime(string $day, string $startTime)
    {
        return $this->schedules->filter(fn ($schedule): bool => $schedule->day_of_week === $day &&
               $schedule->start_time->format('H:i') === $startTime);
    }

    public function getScheduleCardData($schedule): array
    {
        return [
            'subject' => $schedule->class->subject->title ?? 'N/A',
            'faculty' => $schedule->class->faculty ? $schedule->class->faculty->full_name : 'N/A',
            'room' => $schedule->room->name ?? 'N/A',
            'time' => $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i'),
            'section' => $schedule->class->section ?? 'N/A',
            'course_codes' => $schedule->class->course_codes ?? [],
            'student_count' => 0,
            'max_slots' => $schedule->class->maximum_slots ?? 0,
            'class_id' => $schedule->class->id,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            FilamentAction::make('export_timetable_pdf')
                ->label('Export Timetable PDF')
                ->icon('heroicon-o-calendar')
                ->action(function (): void {
                    $this->dispatchPdfGenerationJob('timetable');
                })
                ->visible(
                    fn (): bool => $this->selectedId && $this->schedules->isNotEmpty()
                )
                ->color('primary'),

            FilamentAction::make('export_list_pdf')
                ->label('Export List PDF')
                ->icon('heroicon-o-list-bullet')
                ->action(function (): void {
                    $this->dispatchPdfGenerationJob('list');
                })
                ->visible(
                    fn (): bool => $this->selectedId && $this->schedules->isNotEmpty()
                )
                ->color('primary'),

            FilamentAction::make('export_combined_pdf')
                ->label('Export Combined PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function (): void {
                    $this->dispatchPdfGenerationJob('combined');
                })
                ->visible(
                    fn (): bool => $this->selectedId && $this->schedules->isNotEmpty()
                )
                ->color('primary'),

            FilamentAction::make('export_course_schedule_pdf')
                ->label('Export Course Schedule PDF')
                ->icon('heroicon-o-academic-cap')
                ->action(fn () => $this->exportCourseScheduleToPdf())
                ->visible(
                    fn (): bool => $this->selectedView === 'course' && $this->selectedId && $this->schedules->isNotEmpty()
                )
                ->color('success'),

            FilamentAction::make('export_course_schedule_excel')
                ->label('Export Course Schedule Excel')
                ->icon('heroicon-o-table-cells')
                ->action(fn () => $this->exportCourseScheduleToExcel())
                ->visible(
                    fn (): bool => $this->selectedView === 'course' && $this->selectedId && $this->schedules->isNotEmpty()
                )
                ->color('success'),
        ];
    }

    protected function getFormSchema(): array
    {
        return [
            ComponentsGrid::make(3)->schema([
                Select::make('selectedView')
                    ->label('View By')
                    ->options([
                        'room' => 'Room Schedule',
                        'class' => 'Class Schedule',
                        'student' => 'Student Schedule',
                        'course' => 'Course Schedule',
                        'faculty' => 'Faculty Schedule',
                    ])
                    ->default('room')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state): void {
                        $this->updatedSelectedView($state);
                        // Force a complete re-render of the form
                        $this->dispatch('$refresh');
                    }),

                Select::make('selectedId')
                    ->label(fn (): string => $this->getSecondSelectLabel())
                    ->options(fn (): array => $this->getSecondSelectOptions())
                    ->searchable()
                    ->live(onBlur: true)
                    ->placeholder(fn (): string => $this->getSecondSelectLabel())
                    ->afterStateUpdated(function ($state): void {
                        $this->updatedSelectedId($state);
                    })
                    ->visible(fn (): bool => $this->getSecondSelectOptions() !== [])
                    ->key('selectedId-'.$this->selectedView) // Force re-render when view changes
                    ->reactive(), // Add reactive for better compatibility

                Select::make('selectedYearLevel')
                    ->label('Year Level')
                    ->options([
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                    ])
                    ->searchable()
                    ->live(onBlur: true)
                    ->placeholder('Select Year Level')
                    ->afterStateUpdated(function ($state): void {
                        $this->updatedSelectedYearLevel($state);
                    })
                    ->visible(fn (): bool => $this->selectedView === 'course' && $this->selectedId)
                    ->key('selectedYearLevel-'.$this->selectedView.'-'.$this->selectedId) // Force re-render when course changes
                    ->reactive(),
            ]),
        ];
    }

    protected function getTableQuery(): Builder
    {
        if (! $this->selectedId) {
            return Schedule::query()->whereNull('id');
        }

        // For course view, require both course and year level to be selected
        if ($this->selectedView === 'course' && ! $this->selectedYearLevel) {
            return Schedule::query()->whereNull('id');
        }

        $baseQuery = Schedule::query()->currentAcademicPeriod();

        return match ($this->selectedView) {
            'room' => $baseQuery->clone()
                ->where('room_id', $this->selectedId),

            'class' => $baseQuery->clone()
                ->where('class_id', $this->selectedId),

            'student' => $baseQuery->clone()
                ->whereHas('class.class_enrollments', function ($query): void {
                    $query->where('student_id', $this->selectedId);
                }),

            'course' => $baseQuery->clone()
                ->whereHas('class', function ($query): void {
                    $query->whereJsonContains('course_codes', (int) $this->selectedId)
                        ->where('academic_year', $this->selectedYearLevel);
                }),

            'faculty' => $baseQuery->clone()
                ->whereHas('class', function ($query): void {
                    $query->where('faculty_id', $this->selectedId);
                }),

            default => Schedule::query()->whereNull('id'),
        };
    }

    private function getSecondSelectLabel(): string
    {
        return match ($this->selectedView) {
            'room' => 'Select Room',
            'class' => 'Select Class',
            'student' => 'Select Student',
            'course' => 'Select Course',
            'faculty' => 'Select Faculty',
            default => 'Select',
        };
    }

    private function getSecondSelectOptions(): array
    {
        // Always load fresh options to ensure reactivity
        $options = $this->loadOptionsForView($this->selectedView);
        $this->cachedOptions[$this->selectedView] = $options;

        return $options;
    }

    private function loadOptionsForView(string $view): array
    {
        return match ($view) {
            'room' => Room::query()
                ->orderBy('name')
                ->pluck('name', 'id')
                ->toArray(),

            'class' => Classes::query()
                ->currentAcademicPeriod()
                ->with(['subject', 'faculty'])
                ->get()
                ->filter(fn ($class) => $class->subject)
                ->sortBy([
                    fn ($class) => $class->subject->title ?? 'zzzz',
                    'section',
                ])
                ->mapWithKeys(function ($class): array {
                    $facultyName = $class->faculty
                        ? " (Faculty: {$class->faculty->full_name})"
                        : ' (Faculty: N/A)';
                    $subjectCode = $class->subject
                        ? $class->subject->code
                        : 'N/A';
                    $subjectTitle = $class->subject
                        ? $class->subject->title
                        : 'No Subject Title';

                    // Add course information
                    $courseCodes = $class->formatted_course_codes;
                    $courseInfo = ($courseCodes && $courseCodes !== 'N/A') ? " [{$courseCodes}]" : '';

                    // Add classification
                    $classification = $class->classification ? ' ('.ucfirst((string) $class->classification).')' : ' (College)';

                    return [
                        $class->id => "{$subjectCode} - {$subjectTitle} - Section {$class->section}{$courseInfo}{$classification}{$facultyName}",
                    ];
                })
                ->toArray(),

            'student' => Student::query()
                ->whereHas('classEnrollments', function ($query): void {
                    $query->whereHas('class', function ($q): void {
                        $q->currentAcademicPeriod();
                    });
                })
                ->withCount([
                    'classEnrollments' => function ($query): void {
                        $query->whereHas('class', function ($q): void {
                            $q->currentAcademicPeriod();
                        });
                    },
                ])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(fn ($student): array => [
                    $student->id => "{$student->last_name}, {$student->first_name} (ID: {$student->id}) - {$student->class_enrollments_count} classes",
                ])
                ->toArray(),

            'course' => Course::query()
                ->orderBy('code')
                ->get()
                ->mapWithKeys(fn ($course): array => [
                    $course->id => "{$course->code} - {$course->title} ({$course->department})",
                ])
                ->toArray(),

            'faculty' => Faculty::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->mapWithKeys(function ($faculty): array {
                    // Count current classes for this faculty
                    $classCount = Classes::query()
                        ->currentAcademicPeriod()
                        ->where('faculty_id', $faculty->id)
                        ->count();

                    return [
                        $faculty->id => "{$faculty->full_name} (Department: {$faculty->department}) - {$classCount} classes",
                    ];
                })
                ->toArray(),

            default => [],
        };
    }

    private function exportToPdf(string $format = 'timetable')
    {
        if (! $this->selectedId || $this->schedules->isEmpty()) {
            Notification::make()
                ->title('No schedule data to export.')
                ->warning()
                ->send();

            return null;
        }

        // Prepare enhanced data for export
        $enhancedSchedules = $this->prepareSchedulesForExport();
        $subjectColors = $this->generateSubjectColors($enhancedSchedules);

        $viewData = [
            'selectedView' => $this->selectedView,
            'entityName' => $this->getSelectedEntityName(),
            'schedules' => $enhancedSchedules,
            'days' => $this->getDays(),
            'timeSlots' => $this->getTimeSlots(),
            'currentSchoolYear' => $this->currentSchoolYear,
            'currentSemester' => $this->currentSemester,
            'subjectColors' => $subjectColors,
            'format' => $format,
        ];

        // Choose the appropriate view based on format
        $viewName = match ($format) {
            'list' => 'pdf.schedule-list-export',
            'combined' => 'pdf.schedule-combined-export',
            default => 'pdf.timetable-export',
        };

        $html = view($viewName, $viewData)->render();

        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $entityNameSlug = \Illuminate\Support\Str::slug(
            $this->getSelectedEntityName()
        );
        $filename =
            "schedule_{$format}_{$this->selectedView}_{$entityNameSlug}_".
            now()->format('Y-m-d_His').
            '.pdf';

        $schedulesDir = storage_path('app/public/schedules');
        if (! file_exists($schedulesDir)) {
            mkdir($schedulesDir, 0755, true);
        }
        $outputPath = $schedulesDir.DIRECTORY_SEPARATOR.$filename;

        try {
            // Use PdfGenerationService for Docker/Sail-friendly PDF generation
            $pdfService = app(PdfGenerationService::class);

            $pdfService->generatePdfFromHtml($html, $outputPath, [
                'landscape' => true,
            ]);

            // Verify the file was actually created
            if (! file_exists($outputPath)) {
                throw new Exception("The file \"{$outputPath}\" does not exist. PDF generation may have failed silently.");
            }

            // Verify the file has content
            if (filesize($outputPath) === 0) {
                throw new Exception("The generated PDF file is empty at \"{$outputPath}\".");
            }

            Log::info('Timetable PDF generated successfully', [
                'format' => $format,
                'output_path' => $outputPath,
                'file_size' => filesize($outputPath),
            ]);

            return response()->download($outputPath)->deleteFileAfterSend(true);
        } catch (Exception $e) {
            Log::error('Timetable PDF Generation failed: '.$e->getMessage(), [
                'format' => $format ?? 'unknown',
                'output_path' => $outputPath ?? 'unknown',
                'schedules_dir_exists' => file_exists($schedulesDir ?? ''),
                'temp_dir_exists' => file_exists($tempDir ?? ''),
            ]);
            Log::error('Stack trace: '.$e->getTraceAsString());

            Notification::make()
                ->title('Error generating PDF')
                ->body($e->getMessage()) // Provide more details from the exception
                ->danger()
                ->send();

            return null;
        }
    }

    private function getTimeSlots(): array
    {
        return [
            '07:00',
            '08:00',
            '09:00',
            '10:00',
            '11:00',
            '12:00',
            '13:00',
            '14:00',
            '15:00',
            '16:00',
            '17:00',
            '18:00',
            '19:00',
            '20:00',
            '21:00',
        ];
    }

    private function getDays(): array
    {
        return [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
        ];
    }

    private function getCourseEntityName(): string
    {
        $course = Course::find($this->selectedId);
        if (! $course) {
            return 'Unknown Course';
        }

        $courseName = "{$course->code} - {$course->title}";

        if ($this->selectedYearLevel) {
            $yearLevelText = match ($this->selectedYearLevel) {
                '1' => '1st Year',
                '2' => '2nd Year',
                '3' => '3rd Year',
                '4' => '4th Year',
                default => "{$this->selectedYearLevel} Year"
            };
            $courseName .= " ({$yearLevelText})";
        }

        return $courseName;
    }

    /**
     * Prepare schedules for export with enhanced data
     */
    private function prepareSchedulesForExport(): array
    {
        return $this->schedules->map(fn ($schedule): array => [
            'id' => $schedule->id,
            'day_of_week' => $schedule->day_of_week,
            'start_time' => $schedule->start_time->format('H:i'),
            'end_time' => $schedule->end_time->format('H:i'),
            'start_time_formatted' => $schedule->start_time->format('h:i A'),
            'end_time_formatted' => $schedule->end_time->format('h:i A'),
            'duration_minutes' => $schedule->start_time->diffInMinutes($schedule->end_time),
            'class' => [
                'id' => $schedule->class?->id,
                'section' => $schedule->class?->section,
                'academic_year' => $schedule->class?->academic_year,
                'classification' => $schedule->class?->classification ?? 'college',
                'formatted_course_codes' => $schedule->class?->formatted_course_codes,
                'subject' => [
                    'id' => $schedule->class?->subject?->id,
                    'code' => $schedule->class?->subject?->code,
                    'title' => $schedule->class?->subject?->title,
                    'units' => $schedule->class?->subject?->units,
                ],
                'faculty' => [
                    'id' => $schedule->class?->faculty?->id,
                    'full_name' => $schedule->class?->faculty?->full_name,
                    'department' => $schedule->class?->faculty?->department,
                ],
            ],
            'room' => [
                'id' => $schedule->room?->id,
                'name' => $schedule->room?->name,
            ],
        ])->toArray();
    }

    /**
     * Generate consistent colors for subjects
     */
    private function generateSubjectColors(array $schedules): array
    {
        $colorClasses = [
            'bg-red', 'bg-blue', 'bg-green', 'bg-yellow', 'bg-purple', 'bg-pink',
            'bg-indigo', 'bg-orange', 'bg-teal', 'bg-cyan', 'bg-lime', 'bg-emerald',
            'bg-violet', 'bg-fuchsia', 'bg-rose', 'bg-sky', 'bg-amber',
        ];

        $subjectColors = [];
        $colorIndex = 0;

        foreach ($schedules as $schedule) {
            $subjectCode = $schedule['class']['subject']['code'] ?? null;
            if ($subjectCode && ! isset($subjectColors[$subjectCode])) {
                $subjectColors[$subjectCode] = [
                    'class' => $colorClasses[$colorIndex % count($colorClasses)],
                    'title' => $schedule['class']['subject']['title'] ?? $subjectCode,
                ];
                $colorIndex++;
            }
        }

        return $subjectColors;
    }

    /**
     * Detect conflicts in the current schedules
     */
    private function detectConflicts(): void
    {
        if ($this->schedules->isEmpty()) {
            $this->conflicts = [];
            $this->conflictSummary = [];

            return;
        }

        $conflictService = app(TimetableConflictService::class);
        $cacheKey = "timetable_conflicts_{$this->selectedView}_{$this->selectedId}";

        $this->conflicts = $conflictService->getCachedConflicts($cacheKey, $this->schedules);
        $this->conflictSummary = $conflictService->getConflictSummary($this->conflicts);
    }

    /**
     * Export course schedule to PDF in the requested format
     */
    private function exportCourseScheduleToPdf()
    {
        if ($this->selectedView !== 'course' || ! $this->selectedId || $this->schedules->isEmpty()) {
            Notification::make()
                ->title('No course schedule data to export.')
                ->warning()
                ->send();

            return null;
        }

        // Get all schedules for the course across all year levels
        $allCourseSchedules = $this->getAllCourseSchedules();

        if ($allCourseSchedules->isEmpty()) {
            Notification::make()
                ->title('No schedule data found for this course.')
                ->warning()
                ->send();

            return null;
        }

        $course = Course::find($this->selectedId);
        $schedulesByYear = $this->groupSchedulesByYear($allCourseSchedules);

        // Group schedules within each year by subject and time
        $groupedSchedulesByYear = [];
        foreach ($schedulesByYear as $year => $yearSchedules) {
            $groupedSchedulesByYear[$year] = $this->groupSchedulesBySubjectAndTime($yearSchedules);
        }

        $viewData = [
            'course' => $course,
            'schedulesByYear' => $groupedSchedulesByYear,
            'currentSchoolYear' => $this->currentSchoolYear,
            'currentSemester' => $this->currentSemester,
        ];

        $html = view('pdf.course-schedule-export', $viewData)->render();

        $tempDir = storage_path('app/temp');
        if (! file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $courseCode = \Illuminate\Support\Str::slug($course->code);
        $filename = "course_schedule_{$courseCode}_".now()->format('Y-m-d_His').'.pdf';

        $schedulesDir = storage_path('app/public/schedules');
        if (! file_exists($schedulesDir)) {
            mkdir($schedulesDir, 0755, true);
        }
        $outputPath = $schedulesDir.DIRECTORY_SEPARATOR.$filename;

        try {
            // Use PdfGenerationService for Docker/Sail-friendly PDF generation
            $pdfService = app(PdfGenerationService::class);

            $pdfService->generatePdfFromHtml($html, $outputPath, [
                'landscape' => false,
            ]);

            // Verify the file was actually created
            if (! file_exists($outputPath)) {
                throw new Exception("The file \"{$outputPath}\" does not exist. PDF generation may have failed silently.");
            }

            // Verify the file has content
            if (filesize($outputPath) === 0) {
                throw new Exception("The generated PDF file is empty at \"{$outputPath}\".");
            }

            Log::info('Course Schedule PDF generated successfully', [
                'output_path' => $outputPath,
                'file_size' => filesize($outputPath),
            ]);

            return response()->download($outputPath)->deleteFileAfterSend(true);
        } catch (Exception $e) {
            Log::error('Course Schedule PDF Generation failed: '.$e->getMessage(), [
                'output_path' => $outputPath ?? 'unknown',
                'schedules_dir_exists' => file_exists($schedulesDir ?? ''),
                'temp_dir_exists' => file_exists($tempDir ?? ''),
            ]);

            Notification::make()
                ->title('Error generating PDF')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    /**
     * Export course schedule to Excel (CSV format)
     */
    private function exportCourseScheduleToExcel(): void
    {
        if ($this->selectedView !== 'course' || ! $this->selectedId || $this->schedules->isEmpty()) {
            Notification::make()
                ->title('No course schedule data to export.')
                ->warning()
                ->send();

            return;
        }

        // Get all schedules for the course across all year levels
        $allCourseSchedules = $this->getAllCourseSchedules();

        if ($allCourseSchedules->isEmpty()) {
            Notification::make()
                ->title('No schedule data found for this course.')
                ->warning()
                ->send();

            return;
        }

        $course = Course::find($this->selectedId);
        $schedulesByYear = $this->groupSchedulesByYear($allCourseSchedules);

        // Generate CSV content
        $csvContent = $this->generateCourseScheduleCsv($course, $schedulesByYear);

        $courseCode = \Illuminate\Support\Str::slug($course->code);
        $filename = "course_schedule_{$courseCode}_".now()->format('Y-m-d_His').'.csv';

        // Save to public storage
        $filePath = 'exports/'.$filename;
        Storage::disk('public')->put($filePath, $csvContent);

        // Generate download URL
        $downloadUrl = asset('storage/'.$filePath);

        // Send notification with download action
        Notification::make()
            ->title('Course schedule exported successfully!')
            ->body('Your Excel file is ready for download.')
            ->success()
            ->actions([
                FilamentAction::make('download')
                    ->label('Download Excel File')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url($downloadUrl)
                    ->openUrlInNewTab(),
            ])
            ->persistent()
            ->send();
    }

    /**
     * Get all schedules for the selected course across all year levels
     */
    private function getAllCourseSchedules()
    {
        return Schedule::query()
            ->currentAcademicPeriod()
            ->whereHas('class', function ($query): void {
                $query->whereJsonContains('course_codes', (int) $this->selectedId);
            })
            ->with(['class.subject', 'class.faculty', 'room'])
            ->get();
    }

    /**
     * Group schedules by academic year
     */
    private function groupSchedulesByYear($schedules)
    {
        return $schedules->groupBy(fn ($schedule) => $schedule->class->academic_year ?? 'Unknown')->sortKeys();
    }

    /**
     * Generate CSV content for course schedule
     */
    private function generateCourseScheduleCsv($course, $schedulesByYear): string
    {
        $csv = [];

        // Header
        $csv[] = "Course Schedule: {$course->code} - {$course->title}";
        $csv[] = "School Year: {$this->currentSchoolYear} - Semester: {$this->currentSemester}";
        $csv[] = 'Generated on: '.now()->format('F d, Y H:i:s');
        $csv[] = '';

        foreach ($schedulesByYear as $year => $yearSchedules) {
            $yearText = match ($year) {
                '1' => '1st Year',
                '2' => '2nd Year',
                '3' => '3rd Year',
                '4' => '4th Year',
                default => "Year {$year}"
            };

            $csv[] = $yearText;
            $csv[] = 'Code,Title,Time & Day,Room';

            // Group schedules by subject and time to combine days
            $groupedSchedules = $this->groupSchedulesBySubjectAndTime($yearSchedules);

            foreach ($groupedSchedules as $group) {
                $csv[] = "\"{$group['code_with_section']}\",\"{$group['subject_title']}\",\"{$group['time_and_day']}\",\"{$group['room']}\"";
            }

            $csv[] = '';
        }

        return implode("\n", $csv);
    }

    /**
     * Format time and day for display
     */
    private function formatTimeAndDay($schedule): string
    {
        $startTime = $schedule->start_time->format('H:i');
        $endTime = $schedule->end_time->format('H:i');
        $day = $schedule->day_of_week;

        // Convert day to abbreviated format for compact display
        $dayAbbr = match ($day) {
            'Monday' => 'M',
            'Tuesday' => 'T',
            'Wednesday' => 'W',
            'Thursday' => 'Th',
            'Friday' => 'F',
            'Saturday' => 'S',
            'Sunday' => 'Su',
            default => $day
        };

        return "{$startTime} - {$endTime} {$dayAbbr}";
    }

    /**
     * Group schedules by subject and combine days for same time slots
     */
    private function groupSchedulesBySubjectAndTime($schedules): array
    {
        $grouped = [];

        foreach ($schedules as $schedule) {
            $subjectCode = $schedule->class->subject->code ?? 'N/A';
            $section = $schedule->class->section ?? 'N/A';
            $subjectTitle = $schedule->class->subject->title ?? 'N/A';
            $timeSlot = $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i');
            $room = $schedule->room->name ?? 'N/A';

            // Include section in the key to group by section as well
            $key = "{$subjectCode}|{$section}|{$timeSlot}|{$room}";

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'subject_code' => $subjectCode,
                    'section' => $section,
                    'code_with_section' => "{$subjectCode} - {$section}",
                    'subject_title' => $subjectTitle,
                    'time_slot' => $timeSlot,
                    'room' => $room,
                    'days' => [],
                ];
            }

            $grouped[$key]['days'][] = $schedule->day_of_week;
        }

        // Format days for each group
        foreach ($grouped as &$group) {
            $group['formatted_days'] = $this->formatDays($group['days']);
            $group['time_and_day'] = $group['time_slot'].' '.$group['formatted_days'];
        }

        return array_values($grouped);
    }

    /**
     * Format days array into abbreviated or full format
     */
    private function formatDays(array $days): string
    {
        $days = array_unique($days);
        sort($days);

        // Map to abbreviations
        $dayAbbreviations = [
            'Monday' => 'M',
            'Tuesday' => 'T',
            'Wednesday' => 'W',
            'Thursday' => 'Th',
            'Friday' => 'F',
            'Saturday' => 'S',
            'Sunday' => 'Su',
        ];

        $abbreviated = array_map(fn ($day) => $dayAbbreviations[$day] ?? $day, $days);

        // If 3 or fewer days, use full names with commas and "and"
        if (count($days) <= 3) {
            if (count($days) === 1) {
                return $days[0];
            }
            if (count($days) === 2) {
                return $days[0].' and '.$days[1];
            }

            return $days[0].', '.$days[1].', and '.$days[2];

        }

        // For more than 3 days, use abbreviations
        return implode('', $abbreviated);
    }

    /**
     * Dispatch PDF generation job with the required data.
     */
    private function dispatchPdfGenerationJob(string $format): void
    {
        try {
            // Prepare enhanced data for export (same as before)
            $enhancedSchedules = $this->prepareSchedulesForExport();
            $subjectColors = $this->generateSubjectColors($enhancedSchedules);

            $viewData = [
                'selectedView' => $this->selectedView,
                'entityName' => $this->getSelectedEntityName(),
                'schedules' => $enhancedSchedules,
                'days' => $this->getDays(),
                'timeSlots' => $this->getTimeSlots(),
                'currentSchoolYear' => $this->currentSchoolYear,
                'currentSemester' => $this->currentSemester,
                'subjectColors' => $subjectColors,
                'format' => $format,
            ];

            // Generate filename
            $entityNameSlug = \Illuminate\Support\Str::slug($this->getSelectedEntityName());
            $filename = "timetable-{$entityNameSlug}-{$format}-".date('Y-m-d-His').'.pdf';

            // Dispatch the job with current user ID
            \App\Jobs\GenerateTimetablePdfJob::dispatch($viewData, $filename, $format, Auth::id());

            Notification::make()
                ->title('PDF Generation Started')
                ->body('The timetable PDF is being generated in the background. You will be notified when it is ready.')
                ->info()
                ->send();

        } catch (Exception $e) {
            Notification::make()
                ->title('PDF Generation Failed')
                ->body('There was an error starting the PDF generation: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
