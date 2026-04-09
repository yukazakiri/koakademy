<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Schemas;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Providers\EnrollmentServiceProvider;
use App\Rules\PreviousSemesterCleared;
use App\Services\GeneralSettingsService;
use App\Services\StudentService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Livewire\Component as Livewire;

final class StudentEnrollmentForm
{
    public static function configure(Schema $schema): Schema
    {
        $generalSettingsService = app(GeneralSettingsService::class); // Use the service

        return $schema
            ->components([
                // Select component for Student ID with Create Option
                Select::make('student_id')
                    ->label('Student')
                    ->required()
                    ->rules([
                        new PreviousSemesterCleared(
                            $generalSettingsService->getCurrentSchoolYearString(),
                            $generalSettingsService->getCurrentSemester()
                        ),
                    ])
                    ->validationAttribute('student')
                    ->relationship(name: 'student', titleAttribute: 'last_name') // Use relationship
                    ->getOptionLabelFromRecordUsing(
                        fn (
                            Student $student
                        ): string => sprintf(
                            '%d - %s, %s | %s | %s',
                            $student->id,
                            $student->last_name,
                            $student->first_name,
                            $student->Course?->code ?? 'No Course',
                            $student->formatted_academic_year ?? 'No Year'
                        )
                    ) // Custom label with course and year
                    ->searchable(['id', 'first_name', 'last_name']) // Search multiple columns
                    ->getSearchResultsUsing(fn (string $search): array => Student::query()
                        ->with('Course') // Eager load Course relationship
                        ->where(function ($query) use ($search): void {
                            $query->where('id', 'like', "%{$search}%")
                                ->orWhere('first_name', 'ilike', "%{$search}%")
                                ->orWhere('last_name', 'ilike', "%{$search}%")
                                ->orWhereHas('Course', function ($query) use ($search): void {
                                    $query->where('code', 'ilike', "%{$search}%");
                                });
                        })
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn ($student): array => [
                            $student->id => sprintf(
                                '%d - %s, %s | %s | %s',
                                $student->id,
                                $student->last_name,
                                $student->first_name,
                                $student->Course?->code ?? 'No Course',
                                $student->formatted_academic_year ?? 'No Year'
                            ),
                        ])
                        ->toArray())
                    ->preload()
                    ->live() // Keep live for dependent fields
                    ->createOptionForm([
                        // Modal form for new student
                        Section::make('Basic Information')
                            ->schema([
                                // Student Type Selection (affects ID generation)
                                Select::make('student_type')
                                    ->label('Student Type')
                                    ->options(StudentType::asSelectOptions())
                                    ->required()
                                    ->default(StudentType::College->value)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set): void {
                                        // Update the preview when student type changes
                                        if ($state) {
                                            $studentType = StudentType::from($state);
                                            $nextId = Student::generateNextId($studentType);
                                            $set('preview_student_id', $nextId);
                                        }
                                    }),

                                // Placeholder to preview the next available ID
                                Placeholder::make('preview_student_id')
                                    ->label('Generated Student ID')
                                    ->content(function (Get $get): HtmlString {
                                        $studentTypeValue = $get('student_type') ?? StudentType::College->value;
                                        $studentType = StudentType::from($studentTypeValue);
                                        $nextId = Student::generateNextId($studentType);

                                        return new HtmlString(
                                            sprintf(
                                                "<span class='text-lg font-semibold text-primary-600 dark:text-primary-400'>%d</span> (%s - This ID will be assigned upon creation)",
                                                $nextId,
                                                $studentType->getLabel()
                                            )
                                        );
                                    })
                                    ->live(),

                                // LRN field (conditional on student type)
                                TextInput::make('lrn')
                                    ->label('Learner Reference Number (LRN)')
                                    ->maxLength(255)
                                    ->visible(function (Get $get): bool {
                                        $studentTypeValue = $get('student_type') ?? StudentType::College->value;
                                        $studentType = StudentType::from($studentTypeValue);

                                        return $studentType->requiresLrn();
                                    })
                                    ->required(function (Get $get): bool {
                                        $studentTypeValue = $get('student_type') ?? StudentType::College->value;
                                        $studentType = StudentType::from($studentTypeValue);

                                        return $studentType->requiresLrn();
                                    })
                                    ->helperText('Required for Senior High School students'),
                            ])
                            ->columns(2),

                        Section::make('Personal Details')
                            ->schema([
                                TextInput::make('first_name')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('middle_name')
                                    ->maxLength(20),
                                TextInput::make('email')
                                    ->label('Email address')
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(
                                        table: Student::class,
                                        column: 'email',
                                        ignoreRecord: true
                                    ),
                                Select::make('gender')
                                    ->options([
                                        'male' => 'Male',
                                        'female' => 'Female',
                                    ])
                                    ->required(),
                                DatePicker::make('birth_date')
                                    ->maxDate('today')
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($set, $state): void {
                                        if ($state) {
                                            $age = Carbon::parse($state)->age;
                                            $set('age', $age);
                                        }
                                    })
                                    ->required(),
                                TextInput::make('age')
                                    ->readonly()
                                    ->numeric()
                                    ->required(),
                            ])
                            ->columns(2),

                        Section::make('Academic Information')
                            ->schema([
                                Select::make('course_id')
                                    ->label('Course')
                                    ->relationship('course', 'code')
                                    ->searchable()
                                    ->preload()
                                    ->hidden(fn ($get): bool => $get('student_type') === StudentType::SeniorHighSchool->value)
                                    ->live()
                                    ->afterStateUpdated(function (
                                        Get $get,
                                        Set $set,
                                        $state
                                    ): void {
                                        // Update miscellaneous fee when course changes
                                        if ($state) {
                                            $course = Course::query()->find($state);
                                            $miscellaneousFee =
                                                $course?->getMiscellaneousFee() ?? 3500;
                                            $set('miscellaneous', $miscellaneousFee);

                                            // Recalculate totals
                                            $totalTuition =
                                                (float) ($get('Total_Tuition') ?? 0);
                                            $overallTotal =
                                                $totalTuition + $miscellaneousFee;
                                            $set('overall_total', $overallTotal);

                                            $downpayment =
                                                (float) ($get('downpayment') ?? 0);
                                            $balance = $overallTotal - $downpayment;
                                            $set('total_balance', $balance);
                                        } else {
                                            // Reset to default if no course selected
                                            $set('miscellaneous', 3500);
                                        }
                                    }),
                                Select::make('academic_year')
                                    ->label('Starting Academic Year')
                                    ->options(function ($get): array {
                                        if ($get('student_type') === StudentType::SeniorHighSchool->value) {
                                            return [
                                                '11' => 'Grade 11',
                                                '12' => 'Grade 12',
                                            ];
                                        }

                                        return [
                                            '1' => '1st Year',
                                            '2' => '2nd Year',
                                            '3' => '3rd Year',
                                            '4' => '4th Year',
                                            '5' => 'Graduate',
                                        ];
                                    })
                                    ->required()
                                    ->default('1'),
                                Select::make('status')
                                    ->label('Student Status')
                                    ->options(StudentStatus::class)
                                    ->default(StudentStatus::Enrolled)
                                    ->required()
                                    ->live()
                                    ->helperText('Current enrollment status of the student'),
                            ])
                            ->columns(2),

                        Section::make('SHS Strand')
                            ->schema([
                                Select::make('shs_strand_id')
                                    ->label('SHS Strand')
                                    ->relationship('shsStrand', 'strand_name')
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn ($get): bool => $get('student_type') === StudentType::SeniorHighSchool->value)
                                    ->required(fn ($get): bool => $get('student_type') === StudentType::SeniorHighSchool->value)
                                    ->helperText('Select the SHS strand (e.g., STEM, HUMSS, ABM, GAS, ICT, etc.)'),
                            ])
                            ->columns(2)
                            ->collapsed(),
                    ])
                    ->createOptionAction(
                        fn (Action $action): Action => $action
                            ->modalHeading('Create New Student')
                            ->modalButton('Create Student')
                            ->modalWidth('xl')
                    )
                    ->createOptionUsing(function (
                        array $data,
                        StudentService $studentService
                    ): int {
                        // Inject StudentService
                        $student = $studentService->createStudent($data); // Call the service method

                        return $student instanceof Student ? $student->id : 0; // Return ID on success, 0 on failure
                    })
                    ->afterStateUpdated(function (
                        callable $set,
                        $state,
                        Get $get
                    ): void {
                        // Update dependent fields after selection/creation
                        if ($state) {
                            $student = Student::query()->find($state);
                            if ($student) {
                                // Check if student exists
                                $set('full_name', $student->full_name);
                                $set('guest_email', $student->email ?? '');
                                $set('course_id', $student->course_id ?? '');
                                $set(
                                    'academic_year',
                                    $student->academic_year ?? ''
                                );

                                // Update miscellaneous fee based on student's course
                                if ($student->course_id) {
                                    $course = Course::query()->find($student->course_id);
                                    $miscellaneousFee =
                                        $course?->getMiscellaneousFee() ?? 3500;
                                    $set('miscellaneous', $miscellaneousFee);

                                    // Recalculate totals
                                    $totalTuition =
                                        (float) ($get('Total_Tuition') ?? 0);
                                    $overallTotal =
                                        $totalTuition + $miscellaneousFee;
                                    $set('overall_total', $overallTotal);

                                    $downpayment =
                                        (float) ($get('downpayment') ?? 0);
                                    $balance = $overallTotal - $downpayment;
                                    $set('total_balance', $balance);
                                }

                                // Removed picture_1x1 logic as it's not in the main form state
                            } else {
                                // Clear fields if student not found (e.g., after failed creation)
                                $set('full_name', null);
                                $set('guest_email', null);
                                $set('course_id', null);
                                $set('academic_year', null);
                                $set('miscellaneous', 3500); // Reset to default
                            }
                        } else {
                            // Clear fields if student deselected
                            $set('full_name', null);
                            $set('guest_email', null);
                            $set('course_id', null);
                            $set('academic_year', null);
                            $set('miscellaneous', 3500); // Reset to default
                        }
                    })
                    ->afterStateHydrated(function (
                        Get $get,
                        Set $set,
                        $record
                    ): void {
                        // Populate on edit
                        if (isset($record->student)) {
                            $set('full_name', $record->student->full_name);
                            $set('guest_email', $record->student->email ?? '');
                            $set(
                                'course_id',
                                $record->student->course_id ?? ''
                            );
                            $set(
                                'academic_year',
                                $record->student->academic_year ?? ''
                            );
                            // Removed selected_semester and discount setting here as they belong to enrollment/tuition
                            $set('downpayment', $record->downpayment ?? 0);

                            // Set miscellaneous fee from existing tuition record or course
                            if ($record->studentTuition) {
                                $set(
                                    'miscellaneous',
                                    $record->studentTuition
                                        ->total_miscelaneous_fees
                                );
                            } elseif ($record->course) {
                                $miscellaneousFee = $record->course->getMiscellaneousFee();
                                $set('miscellaneous', $miscellaneousFee);
                            }
                        }
                    }),

                // Semester and Academic Year for the ENROLLMENT (not the student's starting year)
                Select::make('semester')
                    ->label('Enrollment Semester')
                    ->options($generalSettingsService->getAvailableSemesters())
                    ->default($generalSettingsService->getCurrentSemester())
                    ->required(),
                Select::make('academic_year')
                    ->label('Enrollment Academic Year')
                    ->options([
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                    ])
                    ->required()
                    ->live(),

                // Student Info Tab (conditionally visible)
                Tabs::make('Student Info')
                    ->visible(fn (Get $get): bool => $get('student_id') !== null)
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Student Information')
                            ->columns(2)
                            ->schema([
                                Placeholder::make('clearance_status')
                                    ->label('Previous Semester Clearance Status')
                                    ->helperText('Student must be cleared from the previous semester before enrolling')
                                    ->hintActions([
                                        Action::make('quickClearPreviousSemester')
                                            ->label('Quick Clear')
                                            ->icon('heroicon-o-check-circle')
                                            ->color('success')
                                            ->visible(fn (Get $get): bool => $get('student_id') !== null)
                                            ->requiresConfirmation()
                                            ->modalHeading('Quick Clear Previous Semester')
                                            ->modalDescription(function (Get $get): string {
                                                $studentId = $get('student_id');
                                                if (! $studentId) {
                                                    return 'Select a student first';
                                                }
                                                $student = Student::query()->find($studentId);
                                                if (! $student) {
                                                    return 'Student not found';
                                                }
                                                $previous = $student->getPreviousAcademicPeriod();

                                                return "Mark {$student->full_name} as cleared for {$previous['academic_year']} Semester {$previous['semester']}?";
                                            })
                                            ->form([
                                                Toggle::make('is_cleared')
                                                    ->label('Mark as Cleared')
                                                    ->default(true)
                                                    ->helperText('Toggle to mark as cleared or not cleared'),
                                                Textarea::make('remarks')
                                                    ->label('Remarks (Optional)')
                                                    ->placeholder('Enter any notes about this clearance'),
                                            ])
                                            ->action(function (array $data, Get $get, Livewire $livewire): void {
                                                $studentId = $get('student_id');
                                                if (! $studentId) {
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('No Student Selected')
                                                        ->send();

                                                    return;
                                                }

                                                $student = Student::query()->find($studentId);
                                                if (! $student) {
                                                    Notification::make()
                                                        ->danger()
                                                        ->title('Student Not Found')
                                                        ->send();

                                                    return;
                                                }

                                                $previous = $student->getPreviousAcademicPeriod();
                                                $clearance = $student->clearances()
                                                    ->where('academic_year', $previous['academic_year'])
                                                    ->where('semester', $previous['semester'])
                                                    ->first();

                                                $isCleared = $data['is_cleared'] ?? true;

                                                if (! $clearance) {
                                                    $clearance = StudentClearance::query()->create([
                                                        'student_id' => $student->id,
                                                        'academic_year' => $previous['academic_year'],
                                                        'semester' => $previous['semester'],
                                                        'is_cleared' => false,
                                                    ]);
                                                }

                                                $clearance->update([
                                                    'is_cleared' => $isCleared,
                                                    'cleared_by' => $isCleared ? (Auth::user()->name ?? 'Admin') : null,
                                                    'cleared_at' => $isCleared ? now() : null,
                                                    'remarks' => $data['remarks'] ?? $clearance->remarks,
                                                ]);

                                                Notification::make()
                                                    ->success()
                                                    ->title('Clearance Updated')
                                                    ->body("{$student->full_name} has been marked as ".($isCleared ? 'Cleared' : 'Not Cleared')." for {$previous['academic_year']} Semester {$previous['semester']}")
                                                    ->send();

                                                // Refresh the form to show updated clearance status
                                                $livewire->dispatch('$refresh');
                                            }),
                                    ])
                                    ->content(function (Get $get): HtmlString {
                                        $studentId = $get('student_id');

                                        if (! $studentId) {
                                            return new HtmlString('
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="text-sm text-gray-500 dark:text-gray-400">Select a student first</span>
                                                </div>
                                            ');
                                        }

                                        $student = Student::query()->find($studentId);
                                        if (! $student) {
                                            return new HtmlString('
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="text-sm text-red-600 dark:text-red-400 font-medium">Student not found</span>
                                                </div>
                                            ');
                                        }

                                        $generalSettingsService = app(GeneralSettingsService::class);
                                        $validation = $student->validateEnrollmentClearance(
                                            $generalSettingsService->getCurrentSchoolYearString(),
                                            $generalSettingsService->getCurrentSemester()
                                        );

                                        $previousPeriod = $student->getPreviousAcademicPeriod();
                                        $previousSemesterLabel = "{$previousPeriod['academic_year']} - Semester {$previousPeriod['semester']}";

                                        $isCleared = $validation['allowed'];

                                        if ($isCleared) {
                                            return new HtmlString("
                                                <div class='space-y-2'>
                                                    <div class='flex items-center space-x-2'>
                                                        <div class='flex items-center justify-center w-6 h-6 bg-green-100 dark:bg-green-900/20 rounded-full'>
                                                            <svg class='w-4 h-4 text-green-600 dark:text-green-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'></path>
                                                            </svg>
                                                        </div>
                                                        <span class='inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 border border-green-200 dark:border-green-800'>
                                                            ✓ Cleared for {$previousSemesterLabel}
                                                        </span>
                                                    </div>
                                                    <p class='text-xs text-gray-600 dark:text-gray-400 ml-8'>{$validation['message']}</p>
                                                </div>
                                            ");
                                        }

                                        // Not cleared - show error and link to clear previous semester
                                        $clearanceRecord = $validation['clearance'];
                                        $clearanceId = $clearanceRecord ? $clearanceRecord->id : null;

                                        return new HtmlString("
                                            <div class='space-y-3'>
                                                <div class='flex items-center space-x-3'>
                                                    <div class='flex items-center space-x-2'>
                                                        <div class='flex items-center justify-center w-6 h-6 bg-red-100 dark:bg-red-900/20 rounded-full'>
                                                            <svg class='w-4 h-4 text-red-600 dark:text-red-400' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                                                                <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path>
                                                            </svg>
                                                        </div>
                                                        <span class='inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400 border border-red-200 dark:border-red-800'>
                                                            ✗ Not Cleared for {$previousSemesterLabel}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class='ml-8 p-3 bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-lg'>
                                                    <p class='text-sm text-amber-800 dark:text-amber-400 font-medium mb-2'>⚠️ Enrollment Blocked</p>
                                                    <p class='text-xs text-amber-700 dark:text-amber-300'>{$validation['message']}</p>
                                                    <p class='text-xs text-amber-600 dark:text-amber-400 mt-2'>
                                                        Please go to the <a href='/admin/students/{$student->id}' target='_blank' class='font-medium underline hover:text-amber-800 dark:hover:text-amber-200'>Student Profile</a> to manage clearance.
                                                    </p>
                                                </div>
                                            </div>
                                        ");
                                    }),
                                TextInput::make('guest_email') // Consider renaming or removing if redundant
                                    ->label('Student Email')
                                    ->email()
                                    ->columnSpan(1)
                                    ->readOnly()
                                    ->dehydrated(false),
                                TextInput::make('full_name') // Consider renaming or removing if redundant
                                    ->label('Student Full Name')
                                    ->columnSpan(2)
                                    ->readOnly()
                                    ->dehydrated(false),
                                Select::make('course_id') // This should likely be read-only or removed if set by student selection
                                    ->label('Student Course')
                                    ->options(Course::query()->pluck('code', 'id'))
                                    ->disabled() // Make read-only in this context
                                    ->searchable()
                                    ->columnSpan(1),
                            ]),
                    ]),

                // Table Repeater for Subjects
                Repeater::make('subjectsEnrolled')
                    ->table([
                        TableColumn::make('subject code')->width('150px'),
                        TableColumn::make('modular')
                            ->width('100px'),
                        TableColumn::make('subject title')->width('150px'),
                        TableColumn::make('lecture units')->width('120px'),
                        TableColumn::make('lab units')->width('120px'),
                        TableColumn::make('subject lectures')->width('150px'),
                        TableColumn::make('subject laboratory')->width('150px'),
                        TableColumn::make('section')->width('100px'),
                    ])
                    ->relationship('subjectsEnrolled')

                    ->mutateRelationshipDataBeforeCreateUsing(function (
                        array $data,
                        Livewire $livewire
                    ): array {
                        $data['academic_year'] =
                            $livewire->data['academic_year'];
                        $data['school_year'] = self::getCurrentSchoolYear();
                        $data['semester'] = self::getCurrentSemester();
                        $data['student_id'] = $livewire->data['student_id'];

                        // Ensure enrolled units are saved
                        if (empty($data['enrolled_lecture_units']) && ! empty($data['subject_id'])) {
                            $subject = Subject::query()->find($data['subject_id']);
                            if ($subject) {
                                $data['enrolled_lecture_units'] = $subject->lecture;
                            }
                        }

                        if (empty($data['enrolled_laboratory_units']) && ! empty($data['subject_id'])) {
                            $subject = Subject::query()->find($data['subject_id']);
                            if ($subject) {
                                $data['enrolled_laboratory_units'] = $subject->laboratory;
                            }
                        }

                        if (
                            empty($data['lecture_fee']) ||
                            empty($data['laboratory_fee'])
                        ) {
                            $subject = Subject::with('course')->find(
                                $data['subject_id']
                            );
                            if ($subject) {
                                $isNSTP = str_contains(
                                    mb_strtoupper((string) $subject->code),
                                    'NSTP'
                                );
                                $isModular = ! empty($data['is_modular']);
                                $totalUnits =
                                    $subject->lecture + $subject->laboratory;
                                $courseLecPerUnit =
                                    $subject->course?->lec_per_unit ?? 0;
                                // Calculate lecture fee WITHOUT modular addition (modular is separate)
                                $lectureFee = $subject->lecture
                                    ? $totalUnits * $courseLecPerUnit
                                    : 0;
                                if ($isNSTP) {
                                    $lectureFee *= 0.5;
                                }

                                $courseLabPerUnit =
                                    $subject->course?->lab_per_unit ?? 0;
                                $laboratoryFee = $subject->laboratory
                                    ? 1 * $courseLabPerUnit
                                    : 0;
                                // Divide lab fee by 2 for modular subjects
                                if ($isModular && $subject->laboratory) {
                                    $laboratoryFee /= 2;
                                }

                                $data['lecture_fee'] =
                                    $data['lecture'] ?? $lectureFee;
                                $data['laboratory_fee'] =
                                    $data['laboratory'] ?? $laboratoryFee;
                            }
                        }

                        // Remove temporary fields that shouldn't be saved to the database
                        unset(
                            $data['lecture'],
                            $data['laboratory'],
                            $data['title'],
                            $data['pre_riquisite'],
                            $data['subject_code'],
                            $data['section_options']
                        );

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (
                        array $data
                    ): array {
                        $data['lecture_fee'] = $data['lecture'];
                        $data['laboratory_fee'] = $data['laboratory'];

                        // Make sure section is set as class_id
                        if (
                            ! empty($data['section']) &&
                            (empty($data['class_id']) ||
                                $data['class_id'] !== $data['section'])
                        ) {
                            $data['class_id'] = $data['section'];
                        }

                        // Remove temporary fields that shouldn't be saved to the database
                        unset(
                            $data['lecture'],
                            $data['laboratory'],
                            $data['title'],
                            $data['pre_riquisite'],
                            $data['subject_code'],
                            $data['section_options']
                        );

                        return $data;
                    })
                    ->schema([
                        Select::make('subject_id')
                            ->label('Subject (Code/Title)')
                            ->columnSpan(1)
                            ->searchable()
                            ->preload()
                            ->getSearchResultsUsing(function (string $search, Get $get, Livewire $livewire): array {
                                $selectedCourse = $livewire->data['course_id'] ?? null;
                                $studentId = $livewire->data['student_id'] ?? null;

                                // Return empty array if essential data is missing
                                if (empty($selectedCourse) || empty($studentId)) {
                                    return [];
                                }

                                // Get current academic period
                                $schoolYear = self::getCurrentSchoolYear();
                                $semester = self::getCurrentSemester();

                                // Get already enrolled subjects for this student
                                $enrolledSubjectIds = collect($livewire->data['subjectsEnrolled'] ?? [])
                                    ->pluck('subject_id')
                                    ->filter()
                                    ->values()
                                    ->toArray();

                                // Get subjects with classes for the current period
                                // Updated to handle both subject_ids and subject_code
                                $classesWithSubjects = Classes::query()->where('school_year', $schoolYear)
                                    ->where('semester', $semester)
                                    ->get();

                                $subjectsWithClasses = collect();
                                foreach ($classesWithSubjects as $class) {
                                    // Check if this class is for the selected course
                                    $isForCourse = false;
                                    if (! empty($class->course_codes) && is_array($class->course_codes)) {
                                        // course_codes stores IDs, so we need to check if selected course is in the array
                                        // Normalize both sides to string for comparison to handle type mismatches
                                        $selectedCourseStr = (string) $selectedCourse;
                                        $courseCodesAsStrings = array_map(strval(...), $class->course_codes);
                                        $isForCourse = in_array($selectedCourseStr, $courseCodesAsStrings);
                                    }

                                    if (! $isForCourse) {
                                        continue;
                                    }

                                    // Add from subject_ids array
                                    if (! empty($class->subject_ids) && is_array($class->subject_ids)) {
                                        $subjects = Subject::query()->whereIn('id', $class->subject_ids)->get();
                                        foreach ($subjects as $subject) {
                                            $subjectsWithClasses->push($subject->code);
                                        }
                                    }
                                    // Also add from subject_code for backward compatibility
                                    if (! empty($class->subject_code)) {
                                        // Handle comma-separated codes
                                        $codes = array_map(trim(...), explode(',', (string) $class->subject_code));
                                        foreach ($codes as $code) {
                                            if (! empty($code)) {
                                                $subjectsWithClasses->push($code);
                                            }
                                        }
                                    }
                                }
                                $subjectsWithClasses = $subjectsWithClasses->unique();

                                // Search subjects by both code and title
                                $subjects = Subject::query()->where('course_id', $selectedCourse)
                                    ->where(function ($query) use ($search): void {
                                        $query->where('code', 'ilike', sprintf('%%%s%%', $search))
                                            ->orWhere('title', 'ilike', sprintf('%%%s%%', $search));
                                    })
                                    ->whereNotIn('id', $enrolledSubjectIds)
                                    ->orderBy('academic_year')
                                    ->orderBy('semester')
                                    ->orderBy('code')
                                    ->limit(50)
                                    ->get();

                                // Format results with stars, codes, and titles
                                $results = [];
                                foreach ($subjects as $subject) {
                                    $hasClasses = $subjectsWithClasses->contains($subject->code);
                                    $star = $hasClasses ? '⭐ ' : '';
                                    $label = $star.$subject->code.' - '.$subject->title;

                                    // If no classes available, add visual indicator
                                    if (! $hasClasses) {
                                        $label .= ' (⚠️ No Classes)';
                                    }

                                    $results[$subject->id] = $label;
                                }

                                return $results;
                            })
                            ->options(function (Get $get, Livewire $livewire, $state): array {
                                // Get same data as getSearchResultsUsing but without search filter
                                $selectedCourse = $livewire->data['course_id'] ?? null;
                                $studentId = $livewire->data['student_id'] ?? null;

                                // If we have a currently selected subject_id, we need to include it in options
                                // This ensures validation passes even if the options logic changes
                                $currentSubjectId = $state ?? $get('subject_id');

                                // Return empty array if essential data is missing
                                // UNLESS we have a current subject selected (for validation purposes)
                                if (empty($selectedCourse) || empty($studentId)) {
                                    if ($currentSubjectId) {
                                        // Load just the current subject to allow validation to pass
                                        $currentSubject = Subject::with('course')->find($currentSubjectId);
                                        if ($currentSubject) {
                                            return [
                                                $currentSubject->id => $currentSubject->code.' - '.$currentSubject->title,
                                            ];
                                        }
                                    }

                                    return [];
                                }

                                // Get current academic period
                                $schoolYear = self::getCurrentSchoolYear();
                                $semester = self::getCurrentSemester();

                                // Get already enrolled subjects for this student
                                $enrolledSubjectIds = collect($livewire->data['subjectsEnrolled'] ?? [])
                                    ->pluck('subject_id')
                                    ->filter()
                                    ->values()
                                    ->toArray();

                                // Get subjects with classes for the current period
                                $classesWithSubjects = Classes::query()->where('school_year', $schoolYear)
                                    ->where('semester', $semester)
                                    ->get();

                                $subjectsWithClasses = collect();
                                foreach ($classesWithSubjects as $class) {
                                    // Check if this class is for the selected course
                                    $isForCourse = false;
                                    if (! empty($class->course_codes) && is_array($class->course_codes)) {
                                        $selectedCourseStr = (string) $selectedCourse;
                                        $courseCodesAsStrings = array_map(strval(...), $class->course_codes);
                                        $isForCourse = in_array($selectedCourseStr, $courseCodesAsStrings);
                                    }

                                    if (! $isForCourse) {
                                        continue;
                                    }

                                    // Add from subject_ids array
                                    if (! empty($class->subject_ids) && is_array($class->subject_ids)) {
                                        $subjects = Subject::query()->whereIn('id', $class->subject_ids)->get();
                                        foreach ($subjects as $subject) {
                                            $subjectsWithClasses->push($subject->code);
                                        }
                                    }

                                    // Also add from subject_code for backward compatibility
                                    if (! empty($class->subject_code)) {
                                        $codes = array_map(trim(...), explode(',', (string) $class->subject_code));
                                        foreach ($codes as $code) {
                                            if (! empty($code)) {
                                                $subjectsWithClasses->push($code);
                                            }
                                        }
                                    }
                                }
                                $subjectsWithClasses = $subjectsWithClasses->unique();

                                // Get ALL subjects for the student's course
                                $allCourseSubjects = Subject::query()->where('course_id', $selectedCourse)
                                    ->with('course')
                                    ->get()
                                    ->reject(fn ($subject): bool => in_array($subject->id, $enrolledSubjectIds));

                                // Format options with stars and indicators
                                $options = [];
                                foreach ($allCourseSubjects as $allCourseSubject) {
                                    $hasClasses = $subjectsWithClasses->contains($allCourseSubject->code);
                                    $star = $hasClasses ? '⭐ ' : '';
                                    $label = $star.$allCourseSubject->code.' - '.$allCourseSubject->title;

                                    if (! $hasClasses) {
                                        $label .= ' (⚠️ No Classes)';
                                    }

                                    $options[$allCourseSubject->id] = $label;
                                }

                                // CRITICAL: Ensure the currently selected subject is in the options
                                // This prevents validation errors when saving
                                if ($currentSubjectId && ! isset($options[$currentSubjectId])) {
                                    $currentSubject = Subject::with('course')->find($currentSubjectId);
                                    if ($currentSubject) {
                                        // Add it at the beginning to make it clear it's selected
                                        $options = [
                                            $currentSubject->id => '✓ '.$currentSubject->code.' - '.$currentSubject->title.' (Selected)',
                                        ] + $options;
                                    }
                                }

                                return $options;
                            })
                            ->required()
                            ->validationAttribute('subject')
                            ->exists('subject', 'id')
                            ->afterStateUpdated(function (
                                Set $set,
                                $state,
                                Get $get,
                                $record,
                                Livewire $livewire
                            ): void {
                                $selectedCourse =
                                    $livewire->data['course_id'] ?? null;

                                if ($state && $selectedCourse) {
                                    $subject = Subject::with('course')->find(
                                        $state
                                    );

                                    if ($subject) {
                                        $set('title', $subject->title);
                                        $set('subject_code', $subject->code);
                                        $set(
                                            'pre_riquisite',
                                            implode(
                                                ', ',
                                                $subject->pre_riquisite ?? []
                                            )
                                        );

                                        // Set default enrolled units based on subject
                                        $set('enrolled_lecture_units', $subject->lecture ?: 0);
                                        $set('enrolled_laboratory_units', $subject->laboratory ?: 0);

                                        // Calculate fees
                                        $isNSTP = str_contains(
                                            mb_strtoupper((string) $subject->code),
                                            'NSTP'
                                        );
                                        $totalUnits =
                                            $subject->lecture +
                                            $subject->laboratory;
                                        $courseLecPerUnit =
                                            $subject->course?->lec_per_unit ??
                                            0;
                                        $lectureFee = $subject->lecture
                                            ? $totalUnits * $courseLecPerUnit
                                            : 0;
                                        if ($isNSTP) {
                                            $lectureFee *= 0.5;
                                        }

                                        $set('lecture', $lectureFee ?: '0');

                                        $courseLabPerUnit =
                                            $subject->course?->lab_per_unit ??
                                            0;
                                        $laboratoryFee = $subject->laboratory
                                            ? 1 * $courseLabPerUnit
                                            : 0;
                                        $set(
                                            'laboratory',
                                            $laboratoryFee ?: '0'
                                        );

                                        // Get available sections for this subject and course in the correct academic period
                                        $schoolYear = self::getCurrentSchoolYear();
                                        $semester = self::getCurrentSemester();

                                        // Updated query to handle both single and multiple subject structures
                                        $allClasses = Classes::query()->where('school_year', $schoolYear)
                                            ->where('semester', $semester)
                                            ->where(function ($query) use ($subject): void {
                                                // Match classes where subject_ids contains this subject
                                                $query->whereJsonContains('subject_ids', $subject->id)
                                                    // OR where subject_code matches (for backward compatibility)
                                                    ->orWhereRaw(
                                                        'LOWER(TRIM(subject_code)) = LOWER(TRIM(?))',
                                                        [$subject->code]
                                                    )
                                                    // OR where subject_code contains this subject code (comma-separated)
                                                    ->orWhereRaw(
                                                        'LOWER(subject_code) LIKE LOWER(?)',
                                                        ['%'.$subject->code.'%']
                                                    );
                                            })
                                            ->with('Faculty')
                                            ->withCount('class_enrollments')
                                            ->get();

                                        // Filter by course in PHP to handle type variations
                                        $classes = $allClasses->filter(function ($class) use ($selectedCourse): bool {
                                            if (! empty($class->course_codes) && is_array($class->course_codes)) {
                                                // Normalize both sides to string for comparison to handle type mismatches
                                                $selectedCourseStr = (string) $selectedCourse;
                                                $courseCodesAsStrings = array_map(strval(...), $class->course_codes);

                                                return in_array($selectedCourseStr, $courseCodesAsStrings);
                                            }

                                            return false;
                                        });

                                        // Format section options with slot information and class details
                                        $sectionOptions = [];
                                        foreach ($classes as $class) {
                                            $enrolledCount = $class->class_enrollments_count;
                                            $maxSlots = $class->maximum_slots ?: 0;

                                            // Slot information
                                            if ($maxSlots > 0) {
                                                $availableSlots = $maxSlots - $enrolledCount;
                                                $slotInfo = sprintf(' • %s/%s slots', $enrolledCount, $maxSlots);

                                                // Add warning if almost full
                                                if ($availableSlots <= 5 && $availableSlots > 0) {
                                                    $slotInfo .= ' ⚠️';
                                                } elseif ($availableSlots <= 0) {
                                                    $slotInfo .= ' 🚫 FULL';
                                                }
                                            } else {
                                                $slotInfo = ' • Unlimited';
                                            }

                                            // Show faculty name if available
                                            $facultyInfo = $class->Faculty ? ' • '.$class->Faculty->full_name : '';

                                            // Build the label
                                            $label = 'Section '.$class->section.$slotInfo.$facultyInfo;

                                            $sectionOptions[$class->id] = $label;
                                        }

                                        $set('section_options', $sectionOptions);

                                        // Clear section selection when subject changes
                                        $set('section', null);
                                    }
                                } else {
                                    // Clear all fields if no subject selected
                                    $set('title', '');
                                    $set('lecture', 0);
                                    $set('laboratory', 0);
                                    $set('enrolled_lecture_units', 0);
                                    $set('enrolled_laboratory_units', 0);
                                    $set('pre_riquisite', '');
                                    $set('subject_code', '');
                                    $set('section_options', []);
                                    $set('section', null);
                                }

                                // Update totals for both create and edit modes
                                EnrollmentServiceProvider::updateTotals(
                                    $get,
                                    $set,
                                    $record
                                );
                            }),
                        Toggle::make('is_modular')
                            ->label('Is Modular')
                            ->columnSpan(1)
                            ->reactive()
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $record
                            ): void {
                                // Pass $record
                                $subjectId = $get('subject_id');
                                if ($subjectId) {
                                    $subject = Subject::with('course')->find(
                                        $subjectId
                                    );
                                    if ($subject) {
                                        // Calculate the base lecture fee (WITHOUT modular addition)
                                        $totalUnits =
                                            $subject->lecture +
                                            $subject->laboratory;
                                        $courseLecPerUnit =
                                            $subject->course
                                                ?->lec_per_unit ?? 0;
                                        $lectureFee = $subject->lecture
                                            ? $totalUnits *
                                            $courseLecPerUnit
                                            : 0;
                                        $isNSTP = str_contains(
                                            mb_strtoupper((string) $subject->code),
                                            'NSTP'
                                        );
                                        if ($isNSTP) {
                                            $lectureFee *= 0.5;
                                        }

                                        $courseLabPerUnit =
                                            $subject->course
                                                ?->lab_per_unit ?? 0;
                                        $laboratoryFee = $subject->laboratory
                                            ? 1 * $courseLabPerUnit
                                            : 0;

                                        if ($get('is_modular')) {
                                            // For modular: lecture fee stays the same (no +2400), divide lab fee by 2
                                            // The 2400 modular fee is calculated separately in the totals
                                            $set('lecture', $lectureFee ?: '0');
                                            // Divide lab fee by 2 for modular subjects
                                            $modularLabFee = $subject->laboratory ? ($laboratoryFee / 2) : 0;
                                            $set('laboratory', $modularLabFee);
                                            $set('enrolled_lecture_units', $subject->lecture ?: 0);
                                            $set('enrolled_laboratory_units', $subject->laboratory ?: 0);
                                        } else {
                                            // Reset to default fees
                                            $set('enrolled_lecture_units', $subject->lecture ?: 0);
                                            $set('enrolled_laboratory_units', $subject->laboratory ?: 0);
                                            $set('lecture', $lectureFee ?: '0');
                                            $set('laboratory', $laboratoryFee ?: '0');
                                        }
                                    }
                                }

                                EnrollmentServiceProvider::updateTotals(
                                    $get,
                                    $set,
                                    $record
                                ); // Pass $record
                            }),
                        TextInput::make('title')
                            ->label('Subject Title')
                            ->columnSpan(1)
                            ->readOnly()
                            ->dehydrated(false),
                        TextInput::make('enrolled_lecture_units')
                            ->label('Lecture Units')
                            ->columnSpan(1)
                            ->numeric()
                            ->minValue(0)
                            ->live()
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $state,
                                $record
                            ): void {
                                // Get the subject to calculate fees
                                $subjectId = $get('subject_id');
                                if ($subjectId) {
                                    $subject = Subject::with('course')->find($subjectId);
                                    if ($subject && $subject->course) {
                                        // Calculate lecture fee based on enrolled units
                                        $courseLecPerUnit = $subject->course->lec_per_unit ?? 0;
                                        $lectureFee = (int) $state * $courseLecPerUnit;

                                        // Apply NSTP discount if applicable
                                        $isNSTP = str_contains(
                                            mb_strtoupper((string) $subject->code),
                                            'NSTP'
                                        );
                                        if ($isNSTP) {
                                            $lectureFee *= 0.5;
                                        }

                                        $set('lecture', $lectureFee ?: '0');

                                        // Update totals
                                        EnrollmentServiceProvider::updateTotals(
                                            $get,
                                            $set,
                                            $record
                                        );
                                    }
                                }
                            }),
                        TextInput::make('enrolled_laboratory_units')
                            ->label('Laboratory Units')
                            ->columnSpan(1)
                            ->numeric()
                            ->minValue(0)
                            ->live()
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $state,
                                $record
                            ): void {
                                // Get the subject to calculate fees
                                $subjectId = $get('subject_id');
                                if ($subjectId) {
                                    $subject = Subject::with('course')->find($subjectId);
                                    if ($subject && $subject->course) {
                                        // Calculate laboratory fee (always 1 × lab_per_unit if any lab units)
                                        $courseLabPerUnit = $subject->course->lab_per_unit ?? 0;
                                        $laboratoryFee = (int) $state > 0 ? 1 * $courseLabPerUnit : 0;

                                        $set('laboratory', $laboratoryFee ?: '0');

                                        // Update totals
                                        EnrollmentServiceProvider::updateTotals(
                                            $get,
                                            $set,
                                            $record
                                        );
                                    }
                                }
                            }),
                        TextInput::make('lecture')
                            ->label('Lecture Fee')
                            ->columnSpan(1)
                            ->prefix('₱')
                            ->live()
                            ->numeric()
                            ->afterStateUpdated(
                                fn (
                                    Get $get,
                                    Set $set,
                                    $record
                                ) => EnrollmentServiceProvider::updateTotals(
                                    $get,
                                    $set,
                                    $record
                                )
                            ),
                        TextInput::make('laboratory')
                            ->label('Laboratory Fee')
                            ->columnSpan(1)
                            ->prefix('₱')
                            ->live()
                            ->numeric()
                            ->afterStateUpdated(
                                fn (
                                    Get $get,
                                    Set $set,
                                    $record
                                ) => EnrollmentServiceProvider::updateTotals(
                                    $get,
                                    $set,
                                    $record
                                )
                            ),
                        Select::make('section')
                            ->options(function (Get $get, Livewire $livewire): array {
                                $options = is_array($get('section_options'))
                                    ? $get('section_options')
                                    : [];

                                // If options are empty, but we have subject_id, try to load them
                                if (
                                    $options === [] &&
                                    ! empty($get('subject_id'))
                                ) {
                                    $subject = Subject::query()->find($get('subject_id'));
                                    $selectedCourse =
                                        $livewire->data['course_id'] ?? null;

                                    if ($subject && $selectedCourse) {
                                        $schoolYear = self::getCurrentSchoolYear();
                                        $semester = self::getCurrentSemester();

                                        // Updated query to handle multiple subject structures
                                        $allClasses = Classes::query()->where('school_year', $schoolYear)
                                            ->where('semester', $semester)
                                            ->where(function ($query) use ($subject): void {
                                                // Match classes where subject_ids contains this subject
                                                $query->whereJsonContains('subject_ids', $subject->id)
                                                    // OR where subject_code matches (for backward compatibility)
                                                    ->orWhereRaw(
                                                        'LOWER(TRIM(subject_code)) = LOWER(TRIM(?))',
                                                        [$subject->code]
                                                    )
                                                    // OR where subject_code contains this subject code
                                                    ->orWhereRaw(
                                                        'LOWER(subject_code) LIKE LOWER(?)',
                                                        ['%'.$subject->code.'%']
                                                    );
                                            })
                                            ->with('Faculty')
                                            ->withCount('class_enrollments')
                                            ->get();

                                        // Filter by course in PHP to handle type variations
                                        $classes = $allClasses->filter(function ($class) use ($selectedCourse): bool {
                                            if (! empty($class->course_codes) && is_array($class->course_codes)) {
                                                // Normalize both sides to string for comparison to handle type mismatches
                                                $selectedCourseStr = (string) $selectedCourse;
                                                $courseCodesAsStrings = array_map(strval(...), $class->course_codes);

                                                return in_array($selectedCourseStr, $courseCodesAsStrings);
                                            }

                                            return false;
                                        });

                                        // Format section options with enhanced information
                                        $sectionOptions = [];
                                        foreach ($classes as $class) {
                                            $enrolledCount = $class->class_enrollments_count;
                                            $maxSlots = $class->maximum_slots ?: 0;

                                            // Slot information
                                            if ($maxSlots > 0) {
                                                $availableSlots = $maxSlots - $enrolledCount;
                                                $slotInfo = sprintf(' • %s/%s slots', $enrolledCount, $maxSlots);

                                                // Add warning if almost full
                                                if ($availableSlots <= 5 && $availableSlots > 0) {
                                                    $slotInfo .= ' ⚠️';
                                                } elseif ($availableSlots <= 0) {
                                                    $slotInfo .= ' 🚫 FULL';
                                                }
                                            } else {
                                                $slotInfo = ' • Unlimited';
                                            }

                                            // Show faculty name if available
                                            $facultyInfo = $class->Faculty ? ' • '.$class->Faculty->full_name : '';

                                            // Build the label
                                            $label = 'Section '.$class->section.$slotInfo.$facultyInfo;

                                            $sectionOptions[$class->id] = $label;
                                        }

                                        return $sectionOptions;
                                    }
                                }

                                return $options;
                            })
                            ->label('Section')
                            ->columnSpan(1)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (
                                $state,
                                Get $get,
                                Set $set,
                                Livewire $livewire
                            ): void {
                                if ($state) {
                                    $set('class_id', $state);
                                }

                                // Don't call updateTotals directly here with record - it's the wrong type
                                // Instead, just update class_id and let the parent form handle totals
                            })
                            ->afterStateHydrated(function (
                                $state,
                                Get $get,
                                Set $set,
                                $record
                            ): void {
                                // Skip if not in repeater/edit context
                                if (! is_array($record)) {
                                    return;
                                }

                                // If we're editing an existing record with class_id but no section options loaded yet
                                if (
                                    ! empty($record['class_id']) &&
                                    empty($get('section_options'))
                                ) {
                                    // Get the class record
                                    $class = Classes::query()->find($record['class_id']);
                                    if ($class) {
                                        // Get the subject and course to load section options
                                        $subject = Subject::query()->find($record['subject_id']);
                                        if ($subject) {
                                            // Try to get course_id from parent form
                                            $selectedCourse = $get('course_id');
                                            if (
                                                empty($selectedCourse) &&
                                                ! empty($record['course_id'])
                                            ) {
                                                $selectedCourse =
                                                    $record['course_id'];
                                            }

                                            if ($selectedCourse) {
                                                $schoolYear = self::getCurrentSchoolYear();
                                                $semester = self::getCurrentSemester();

                                                // Updated query to handle multiple subject structures
                                                $allClasses = Classes::query()->where('school_year', $schoolYear)
                                                    ->where('semester', $semester)
                                                    ->where(function ($query) use ($subject): void {
                                                        // Match classes where subject_ids contains this subject
                                                        $query->whereJsonContains('subject_ids', $subject->id)
                                                            // OR where subject_code matches
                                                            ->orWhereRaw(
                                                                'LOWER(subject_code) = LOWER(?)',
                                                                [$subject->code]
                                                            )
                                                            // OR where subject_code contains this subject code
                                                            ->orWhereRaw(
                                                                'LOWER(subject_code) LIKE LOWER(?)',
                                                                ['%'.$subject->code.'%']
                                                            );
                                                    })
                                                    ->with('Faculty')
                                                    ->withCount('class_enrollments')
                                                    ->get();

                                                // Filter by course in PHP to handle type variations
                                                $classes = $allClasses->filter(function ($class) use ($selectedCourse): bool {
                                                    if (! empty($class->course_codes) && is_array($class->course_codes)) {
                                                        return in_array($selectedCourse, $class->course_codes) ||
                                                            in_array((string) $selectedCourse, $class->course_codes) ||
                                                            in_array((int) $selectedCourse, $class->course_codes);
                                                    }

                                                    return false;
                                                });

                                                if (! empty($classes) && $classes->count() > 0) {
                                                    // Format section options with enhanced information
                                                    $sectionOptions = [];
                                                    foreach ($classes as $class) {
                                                        $enrolledCount = $class->class_enrollments_count;
                                                        $maxSlots = $class->maximum_slots ?: 0;

                                                        // Slot information
                                                        if ($maxSlots > 0) {
                                                            $availableSlots = $maxSlots - $enrolledCount;
                                                            $slotInfo = sprintf(' • %s/%s slots', $enrolledCount, $maxSlots);

                                                            if ($availableSlots <= 5 && $availableSlots > 0) {
                                                                $slotInfo .= ' ⚠️';
                                                            } elseif ($availableSlots <= 0) {
                                                                $slotInfo .= ' 🚫 FULL';
                                                            }
                                                        } else {
                                                            $slotInfo = ' • Unlimited';
                                                        }

                                                        // Show faculty name if available
                                                        $facultyInfo = $class->Faculty ? ' • '.$class->Faculty->full_name : '';

                                                        // Build the label
                                                        $label = 'Section '.$class->section.$slotInfo.$facultyInfo;

                                                        $sectionOptions[$class->id] = $label;
                                                    }

                                                    $set('section_options', $sectionOptions);
                                                    // Now set the section value
                                                    $set('section', $record['class_id']);
                                                }
                                            }
                                        }
                                    }
                                }

                                // If we have section_options but no value set, check if we have class_id to set
                                if (
                                    empty($state) &&
                                    ! empty($record['class_id']) &&
                                    ! empty($get('section_options'))
                                ) {
                                    $set('section', $record['class_id']);
                                }
                            }),

                        TextInput::make('pre_riquisite')
                            ->label('Pre-requisites')
                            ->visible(false)
                            ->readOnly()
                            ->dehydrated(false),
                        Hidden::make('subject_code')
                            ->dehydrated(false),
                        Hidden::make('class_id'),
                        Hidden::make('section_options')
                            ->dehydrated(false),
                    ])
                    // ->showLabels(false)
                    // ->emptyLabel('Enroll a Subject for this Student')
                    ->columnSpanFull()
                    // ->streamlined()
                    ->live()
                    ->afterStateHydrated(function (
                        Get $get,
                        Set $set,
                        $record
                    ): void {
                        // Pass Set as nullable
                        $subjectsEnrolled = $get('subjectsEnrolled');
                        foreach ($subjectsEnrolled as $index => $subject) {
                            if (! empty($subject['subject_id'])) {
                                $subjectEnrollment = SubjectEnrollment::with([
                                    'subject.course',
                                    'class',
                                ])->find($subject['id'] ?? null); // Handle potential null ID
                                if ($subjectEnrollment) {
                                    $set(
                                        'subjectsEnrolled.'.$index.'.title',
                                        $subjectEnrollment->subject->title
                                    );
                                    $set(
                                        'subjectsEnrolled.'.
                                            $index.
                                            '.subject_id',
                                        $subjectEnrollment->subject_id
                                    ); // Ensure subject_id is set
                                    $set(
                                        'subjectsEnrolled.'.
                                            $index.
                                            '.subject_code',
                                        $subjectEnrollment->subject->code ?? ''
                                    );

                                    if (
                                        $subjectEnrollment->lecture_fee !==
                                        null &&
                                        $subjectEnrollment->laboratory_fee !==
                                        null
                                    ) {
                                        $set(
                                            'subjectsEnrolled.'.
                                                $index.
                                                '.lecture',
                                            $subjectEnrollment->lecture_fee
                                        );
                                        $set(
                                            'subjectsEnrolled.'.
                                                $index.
                                                '.laboratory',
                                            $subjectEnrollment->laboratory_fee
                                        );
                                    } else {
                                        $subjectModel =
                                            $subjectEnrollment->subject;
                                        $isNSTP = str_contains(
                                            mb_strtoupper(
                                                (string) $subjectModel->code
                                            ),
                                            'NSTP'
                                        );
                                        $isModular = $subjectEnrollment->is_modular ?? false;
                                        $totalUnits =
                                            $subjectModel->lecture +
                                            $subjectModel->laboratory;
                                        $courseLecPerUnit =
                                            $subjectModel->course
                                                ?->lec_per_unit ?? 0;
                                        // Calculate lecture fee WITHOUT modular addition (modular is separate)
                                        $lectureFee = $subjectModel->lecture
                                            ? $totalUnits * $courseLecPerUnit
                                            : 0;
                                        if ($isNSTP) {
                                            $lectureFee *= 0.5;
                                        }

                                        $courseLabPerUnit =
                                            $subjectModel->course
                                                ?->lab_per_unit ?? 0;
                                        $laboratoryFee = $subjectModel->laboratory
                                            ? 1 * $courseLabPerUnit
                                            : 0;
                                        // Divide lab fee by 2 for modular subjects
                                        if ($isModular && $subjectModel->laboratory) {
                                            $laboratoryFee /= 2;
                                        }

                                        $set(
                                            'subjectsEnrolled.'.
                                                $index.
                                                '.lecture',
                                            $lectureFee
                                        );
                                        $set(
                                            'subjectsEnrolled.'.
                                                $index.
                                                '.laboratory',
                                            $laboratoryFee
                                        );
                                        $subjectEnrollment->update([
                                            'lecture_fee' => $lectureFee,
                                            'laboratory_fee' => $laboratoryFee,
                                        ]);
                                    }

                                    $set(
                                        'subjectsEnrolled.'.
                                            $index.
                                            '.is_modular',
                                        $subjectEnrollment->is_modular
                                    );

                                    // Also set the class_id and section if available
                                    if ($subjectEnrollment->class_id) {
                                        $set(
                                            'subjectsEnrolled.'.
                                                $index.
                                                '.class_id',
                                            $subjectEnrollment->class_id
                                        );

                                        // Load section options and set the selected section
                                        $subject = $subjectEnrollment->subject;
                                        $student = $record->student;
                                        if ($subject && $student) {
                                            $selectedCourse =
                                                $student->course_id ?? null;
                                            if ($selectedCourse) {
                                                $schoolYear = self::getCurrentSchoolYear();
                                                $semester = self::getCurrentSemester();

                                                // Updated query to handle multiple subject structures
                                                $allClasses = Classes::query()->where('school_year', $schoolYear)
                                                    ->where('semester', $semester)
                                                    ->where(function ($query) use ($subject): void {
                                                        // Match classes where subject_ids contains this subject
                                                        $query->whereJsonContains('subject_ids', $subject->id)
                                                            // OR where subject_code matches
                                                            ->orWhereRaw(
                                                                'LOWER(subject_code) = LOWER(?)',
                                                                [$subject->code]
                                                            )
                                                            // OR where subject_code contains this subject code
                                                            ->orWhereRaw(
                                                                'LOWER(subject_code) LIKE LOWER(?)',
                                                                ['%'.$subject->code.'%']
                                                            );
                                                    })
                                                    ->with('Faculty')
                                                    ->withCount('class_enrollments')
                                                    ->get();

                                                // Filter by course in PHP to handle type variations
                                                $classes = $allClasses->filter(function ($class) use ($selectedCourse): bool {
                                                    if (! empty($class->course_codes) && is_array($class->course_codes)) {
                                                        return in_array($selectedCourse, $class->course_codes) ||
                                                            in_array((string) $selectedCourse, $class->course_codes) ||
                                                            in_array((int) $selectedCourse, $class->course_codes);
                                                    }

                                                    return false;
                                                });

                                                // Format section options with enhanced information
                                                $sectionOptions = [];
                                                foreach ($classes as $class) {
                                                    $enrolledCount = $class->class_enrollments_count;
                                                    $maxSlots = $class->maximum_slots ?: 0;

                                                    // Slot information
                                                    if ($maxSlots > 0) {
                                                        $availableSlots = $maxSlots - $enrolledCount;
                                                        $slotInfo = sprintf(' • %s/%s slots', $enrolledCount, $maxSlots);

                                                        // Add warning if almost full
                                                        if ($availableSlots <= 5 && $availableSlots > 0) {
                                                            $slotInfo .= ' ⚠️';
                                                        } elseif ($availableSlots <= 0) {
                                                            $slotInfo .= ' 🚫 FULL';
                                                        }
                                                    } else {
                                                        $slotInfo = ' • Unlimited';
                                                    }

                                                    // Show faculty name if available
                                                    $facultyInfo = $class->Faculty ? ' • '.$class->Faculty->full_name : '';

                                                    // Build the label
                                                    $label = 'Section '.$class->section.$slotInfo.$facultyInfo;

                                                    $sectionOptions[$class->id] = $label;
                                                }

                                                $set(
                                                    'subjectsEnrolled.'.
                                                        $index.
                                                        '.section_options',
                                                    $sectionOptions
                                                );
                                                $set(
                                                    'subjectsEnrolled.'.
                                                        $index.
                                                        '.section',
                                                    $subjectEnrollment->class_id
                                                );
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        EnrollmentServiceProvider::updateTotals(
                            $get,
                            $set,
                            $record
                        );
                    })
                    ->afterStateUpdated(
                        fn (
                            Get $get,
                            Set $set,
                            $record
                        ) => EnrollmentServiceProvider::updateTotals(
                            $get,
                            $set,
                            $record
                        )
                    )
                    ->deleteAction(
                        fn (Action $action): Action => $action->after(
                            fn (
                                Get $get,
                                Set $set,
                                $record
                            ) => EnrollmentServiceProvider::updateTotals(
                                $get,
                                $set,
                                $record
                            )
                        )
                    ),

                // Class Schedules Section
                Section::make('Class Schedules')
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('class_schedules')
                            ->label('Class Schedules')
                            ->live() // Make it reactive to changes
                            ->content(function (Get $get): HtmlString {
                                // Removed Set $set as it's not used
                                $subjectsEnrolled = $get('subjectsEnrolled');
                                $scheduleData = [];

                                if (empty($subjectsEnrolled)) {
                                    return new HtmlString(
                                        '<p class="text-sm text-gray-500 dark:text-gray-400">No subjects enrolled yet. Select subjects and sections to see schedules.</p>'
                                    );
                                }

                                foreach ($subjectsEnrolled as $subjectEnrolled) {
                                    // Check both section and class_id fields
                                    $sectionId =
                                        $subjectEnrolled['section'] ??
                                        ($subjectEnrolled['class_id'] ?? null);

                                    if (! empty($sectionId)) {
                                        $class = Classes::with(
                                            'Schedule.room'
                                        )->find($sectionId);
                                        if ($class && $class->Schedule) {
                                            $subjectCode =
                                                $subjectEnrolled['subject_code'] ??
                                                null;
                                            if (
                                                empty($subjectCode) &&
                                                ! empty($subjectEnrolled['subject_id'])
                                            ) {
                                                $subjectModel = Subject::query()->find($subjectEnrolled['subject_id']);
                                                $subjectCode = $subjectModel
                                                    ? $subjectModel->code
                                                    : 'Unknown';
                                            }

                                            foreach (
                                                $class->Schedule as $schedule
                                            ) {
                                                $weekDay = mb_strtolower(
                                                    (string) $schedule->day_of_week
                                                );
                                                $startTime = date(
                                                    'g:i A',
                                                    strtotime(
                                                        (string) $schedule->start_time
                                                    )
                                                );
                                                $endTime = date(
                                                    'g:i A',
                                                    strtotime(
                                                        (string) $schedule->end_time
                                                    )
                                                );
                                                $room =
                                                    $schedule->room->name ??
                                                    'TBA';
                                                $section =
                                                    $class->section ?? '';
                                                $scheduleData[$subjectCode][$weekDay] = sprintf('<b>%s | %s</b> <br> %s - %s', $room, $section, $startTime, $endTime);
                                            }
                                        }
                                    }
                                }

                                // If no schedule data found, show helpful message
                                if ($scheduleData === []) {
                                    return new HtmlString(
                                        '<p class="text-sm text-gray-500 dark:text-gray-400">No schedules available. Make sure you have selected sections for your subjects.</p>'
                                    );
                                }

                                // Build HTML table (keep existing logic)
                                $table =
                                    '<table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">';
                                $table .=
                                    '<thead class="bg-gray-50 dark:bg-gray-800"><tr>';
                                $table .=
                                    '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Subject</th>';
                                $daysHeader = [
                                    'Mon',
                                    'Tue',
                                    'Wed',
                                    'Thu',
                                    'Fri',
                                    'Sat',
                                ];
                                foreach ($daysHeader as $day) {
                                    $table .=
                                        '<th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">'.
                                        $day.
                                        '</th>';
                                }

                                $table .= '</tr></thead>';
                                $table .=
                                    '<tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">';
                                foreach (
                                    $scheduleData as $subjectCode => $schedule
                                ) {
                                    $table .=
                                        '<tr><td class="px-6 py-4 whitespace-nowrap">'.
                                        $subjectCode.
                                        '</td>';
                                    $days = [
                                        'monday',
                                        'tuesday',
                                        'wednesday',
                                        'thursday',
                                        'friday',
                                        'saturday',
                                    ];
                                    foreach ($days as $day) {
                                        $content = $schedule[$day] ?? '';
                                        $cellClass =
                                            $content !== '' && $content !== '0'
                                            ? 'bg-primary-500/50 text-primary-300 border border-primary-500'
                                            : '';
                                        $table .=
                                            '<td class="px-1 text-center '.
                                            $cellClass.
                                            '">'.
                                            $content.
                                            '</td>';
                                    }

                                    $table .= '</tr>';
                                }

                                $table .= '</tbody></table>';

                                return new HtmlString($table);
                            }),
                    ]),

                // Total Units Summary
                Section::make('Total Units Summary')
                    ->description('Summary of units being enrolled')
                    ->columnSpanFull()
                    ->schema([
                        Placeholder::make('total_units_summary')
                            ->label('Total Units')
                            ->live()
                            ->content(function (Get $get): HtmlString {
                                $subjectsEnrolled = $get('subjectsEnrolled');

                                if (empty($subjectsEnrolled)) {
                                    return new HtmlString(
                                        '<div class="text-sm text-gray-500 dark:text-gray-400">No subjects enrolled yet.</div>'
                                    );
                                }

                                $totalLectureUnits = 0;
                                $totalLaboratoryUnits = 0;
                                $totalUnits = 0;
                                $subjectsCount = 0;

                                foreach ($subjectsEnrolled as $subject) {
                                    if (! empty($subject['subject_id'])) {
                                        $subjectsCount++;
                                        $totalLectureUnits += (int) ($subject['enrolled_lecture_units'] ?? 0);
                                        $totalLaboratoryUnits += (int) ($subject['enrolled_laboratory_units'] ?? 0);
                                    }
                                }

                                $totalUnits = $totalLectureUnits + $totalLaboratoryUnits;

                                return new HtmlString(
                                    '<div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">'.$subjectsCount.'</div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Subjects</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">'.$totalLectureUnits.'</div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Lecture Units</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">'.$totalLaboratoryUnits.'</div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Laboratory Units</div>
                                            </div>
                                            <div class="text-center">
                                                <div class="text-3xl font-bold text-indigo-600 dark:text-indigo-400">'.$totalUnits.'</div>
                                                <div class="text-sm text-gray-600 dark:text-gray-400">Total Units</div>
                                            </div>
                                        </div>
                                    </div>'
                                );
                            }),
                    ]),

                // Assessment Section
                Section::make('Assessment')
                    ->description(
                        'This section is for the assessment of the student'
                    )
                    ->columnSpanFull()
                    ->afterStateHydrated(function (Set $set, $record): void {
                        // Ensure all assessment fields are properly loaded when editing
                        if ($record && $record->studentTuition) {
                            try {
                                $tuition = $record->studentTuition;

                                // Set discount - always set from tuition record
                                $set('discount', (string) ($tuition->discount ?? 0));

                                // Set other tuition fields with null safety
                                $set('total_lectures', $tuition->total_lectures ?? 0);
                                $set('total_laboratory', $tuition->total_laboratory ?? 0);
                                $set('Total_Tuition', $tuition->total_tuition ?? 0);
                                $set('miscellaneous', $tuition->total_miscelaneous_fees ?? 3500);
                                $set('overall_total', $tuition->overall_tuition ?? 0);
                                $set('downpayment', $tuition->downpayment ?? 0);
                                $set('total_balance', $tuition->total_balance ?? 0);

                                // Set original lecture amount for discount calculations
                                // Calculate the original lecture amount before discount was applied
                                $discount = (int) ($tuition->discount ?? 0);
                                $totalLectures = (float) ($tuition->total_lectures ?? 0);

                                if ($discount > 0 && $discount < 100 && $totalLectures > 0) {
                                    // Reverse calculate the original lecture amount
                                    $discountMultiplier = 1 - $discount / 100;
                                    if ($discountMultiplier > 0) {
                                        $originalLecture = $totalLectures / $discountMultiplier;
                                        $set('original_lecture_amount', $originalLecture);
                                    } else {
                                        // Fallback if discount multiplier is invalid
                                        $set('original_lecture_amount', $totalLectures);
                                    }
                                } else {
                                    // No discount, 100% discount, or zero lectures - use current value
                                    $set('original_lecture_amount', $totalLectures);
                                }

                                // Mark as manually modified if tuition record exists
                                $set('is_manually_modified', true);
                            } catch (Exception $e) {
                                // Log error and set default values
                                Log::error('Error hydrating assessment fields', [
                                    'enrollment_id' => $record->id ?? 'unknown',
                                    'error' => $e->getMessage(),
                                ]);

                                // Set safe default values
                                $set('discount', '0');
                                $set('total_lectures', 0);
                                $set('total_laboratory', 0);
                                $set('Total_Tuition', 0);
                                $set('miscellaneous', 3500);
                                $set('overall_total', 3500);
                                $set('downpayment', 0);
                                $set('total_balance', 3500);
                                $set('original_lecture_amount', 0);
                                $set('is_manually_modified', false);
                            }
                        }
                    })
                    ->schema([
                        Hidden::make(
                            'is_manually_modified'
                        )->default(
                            fn ($record): bool => $record &&
                                $record->studentTuition !== null
                        ),
                        Select::make('discount')
                            ->label('Discount')
                            ->live()
                            ->options(function (): array {
                                $options = ['0' => 'No Discount'];
                                for ($i = 5; $i <= 100; $i += 5) {
                                    $options[(string) $i] = $i.'% Discount';
                                }

                                return $options;
                            })
                            ->default(function ($record, $state) {
                                // If we already have a state (form is being filled), use it
                                if ($state !== null) {
                                    return $state;
                                }

                                // For existing records, get discount from tuition record
                                if ($record && $record->studentTuition) {
                                    return (string) $record->studentTuition->discount;
                                }

                                // Default for new records
                                return '0';
                            })
                            ->dehydrated(true) // Ensure the value is always included in form data
                            ->afterStateHydrated(function (
                                Set $set,
                                $record
                            ): void {
                                // Ensure discount is properly loaded when editing existing records
                                if ($record && $record->studentTuition) {
                                    $discountValue = (string) $record->studentTuition->discount;
                                    // Always set the discount from the tuition record when editing
                                    $set('discount', $discountValue);
                                }
                            })
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $record
                            ): void {
                                if (! $get('original_lecture_amount')) {
                                    $set(
                                        'original_lecture_amount',
                                        $get('total_lectures')
                                    );
                                }

                                $set('is_manually_modified', true);
                                EnrollmentServiceProvider::recalculateTotals(
                                    $get,
                                    $set,
                                    $record
                                );
                            })
                            ->suffix('%'),
                        Hidden::make(
                            'original_lecture_amount'
                        ),
                        Hidden::make(
                            'is_overall_manually_modified'
                        )->default(false),
                        Hidden::make(
                            'original_overall_amount'
                        ),
                        TextInput::make('total_lectures')
                            ->numeric()
                            ->prefix('₱')
                            ->live(onBlur: true)
                            ->default(
                                fn ($record) => $record &&
                                    $record->studentTuition
                                    ? $record->studentTuition->total_lectures
                                    : null
                            )
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $record
                            ): void {
                                $set('is_manually_modified', true);
                                $set(
                                    'original_lecture_amount',
                                    $get('total_lectures')
                                );
                                EnrollmentServiceProvider::recalculateTotals(
                                    $get,
                                    $set,
                                    $record
                                );
                            })
                            ->label('Total Lecture'),
                        TextInput::make('total_laboratory')
                            ->numeric()
                            ->prefix('₱')
                            ->live(onBlur: true)
                            ->default(
                                fn ($record) => $record &&
                                    $record->studentTuition
                                    ? $record->studentTuition->total_laboratory
                                    : null
                            )
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $record
                            ): void {
                                $set('is_manually_modified', true);
                                EnrollmentServiceProvider::recalculateTotals(
                                    $get,
                                    $set,
                                    $record
                                );
                            })
                            ->label('Total Laboratory'),
                        TextInput::make('Total_Tuition')
                            ->numeric()
                            ->prefix('₱')
                            ->live()
                            ->label('Total Tuition'),
                        TextInput::make('miscellaneous')
                            ->numeric()
                            ->prefix('₱')
                            ->live(onBlur: true)
                            ->default(function (Get $get, $record) {
                                // For existing records, use the tuition record value
                                if ($record && $record->studentTuition) {
                                    return $record->studentTuition
                                        ->total_miscelaneous_fees;
                                }

                                // For new records, get from course or default
                                $courseId = $get('course_id');
                                if ($courseId) {
                                    $course = Course::query()->find($courseId);

                                    return $course?->miscelaneous ?? 3500;
                                }

                                return 3500; // Final fallback
                            })
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $record
                            ): void {
                                // Use the centralized update method that includes additional fees
                                EnrollmentServiceProvider::updateTotals($get, $set, $record);
                            })
                            ->label('Miscellaneous'),

                        // Additional Fees Repeater
                        Repeater::make('additionalFees')
                            ->label('Additional Fees')
                            ->relationship('additionalFees')
                            ->defaultItems(0)
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('fee_name')
                                            ->label('Fee Name')
                                            ->maxLength(255)
                                            ->placeholder('e.g., School Kit Fee, Library Fee, etc.')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $record): void {
                                                EnrollmentServiceProvider::updateTotals($get, $set, $record);
                                            }),
                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->prefix('₱')
                                            ->minValue(0)
                                            ->step(0.01)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Get $get, Set $set, $record): void {
                                                // Use a more reliable method to update totals
                                                // Call the main update function that handles all calculations
                                                EnrollmentServiceProvider::updateTotals($get, $set, $record);
                                            }),
                                        Toggle::make('is_separate_transaction')
                                            ->label('Separate Transaction')
                                            ->helperText('Enable if this fee requires a separate payment transaction')
                                            ->default(false)
                                            ->live()
                                            ->afterStateUpdated(function (Get $get, Set $set, $record): void {
                                                EnrollmentServiceProvider::updateTotals($get, $set, $record);
                                            }),
                                    ]),
                            ])
                            ->addActionLabel('Add Additional Fee')
                            ->reorderable()
                            ->collapsible()
                            ->collapsed()
                            ->columnSpanFull()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $record): void {
                                // Trigger update when items are added/removed
                                EnrollmentServiceProvider::updateTotals($get, $set, $record);
                            })
                            ->deleteAction(
                                fn (Action $action): Action => $action
                                    ->after(function (Get $get, Set $set, $record): void {
                                        // Small delay to ensure the item is actually deleted from the form state
                                        EnrollmentServiceProvider::updateTotals($get, $set, $record);
                                    })
                                    ->requiresConfirmation()
                                    ->modalHeading('Delete Additional Fee')
                                    ->modalDescription('Are you sure you want to delete this additional fee? This will update the total calculation.')
                            )
                            ->addAction(
                                fn (Action $action): Action => $action->after(function (Get $get, Set $set, $record): void {
                                    // Trigger update when items are added
                                    EnrollmentServiceProvider::updateTotals($get, $set, $record);
                                })
                            ),

                        // Hidden field to track additional fees changes and trigger updates
                        Hidden::make('additional_fees_trigger')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $record): void {
                                // This will be triggered when the hidden field changes
                                EnrollmentServiceProvider::updateTotals($get, $set, $record);
                            })
                            ->default(function (Get $get): string {
                                // Create a hash of the additional fees to detect changes
                                $fees = $get('additionalFees') ?? [];

                                return md5(json_encode($fees));
                            }),

                        // Real-time calculated total (for immediate feedback)
                        Placeholder::make('calculated_total')
                            ->label('Calculated Total (Live)')
                            ->content(function (Get $get): string {
                                $totalTuition = (float) ($get('Total_Tuition') ?? 0);
                                $miscellaneous = (float) ($get('miscellaneous') ?? 0);
                                $additionalFees = collect($get('additionalFees') ?? [])
                                    ->sum(fn ($fee): float => (float) ($fee['amount'] ?? 0));

                                $calculatedTotal = $totalTuition + $miscellaneous + $additionalFees;

                                return '₱ '.number_format($calculatedTotal, 2);
                            })
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('overall_total')
                            ->numeric()
                            ->live()
                            ->reactive()
                            ->prefix('₱')
                            ->default(function (Get $get, $record) {
                                if ($record && $record->studentTuition) {
                                    return $record->studentTuition->overall_tuition;
                                }

                                // Calculate from form data if no record
                                $totalTuition = (float) ($get('Total_Tuition') ?? 0);
                                $miscellaneous = (float) ($get('miscellaneous') ?? 0);
                                $additionalFees = collect($get('additionalFees') ?? [])
                                    ->sum(fn ($fee): float => (float) ($fee['amount'] ?? 0));

                                return $totalTuition + $miscellaneous + $additionalFees;
                            })
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                $record
                            ): void {
                                // Mark as manually modified and store original value
                                $set('is_overall_manually_modified', true);
                                $set(
                                    'original_overall_amount',
                                    $get('overall_total')
                                );

                                // Recalculate balance when overall total changes
                                $overallTotal =
                                    (float) ($get('overall_total') ?? 0);
                                $downpayment =
                                    (float) ($get('downpayment') ?? 0);
                                $balance = $overallTotal - $downpayment;
                                $set('total_balance', $balance);

                                // Persist changes if in edit context
                                if ($record instanceof StudentEnrollment) {
                                    EnrollmentServiceProvider::updateOrCreateTuitionWithManualOverride(
                                        $record,
                                        $get,
                                        $overallTotal
                                    );
                                }
                            })
                            ->helperText(fn (Get $get): string => (bool) $get(
                                'is_overall_manually_modified'
                            )
                                ? '⚠️ This value has been manually modified and will not auto-calculate.'
                                : 'This value is automatically calculated. Edit to override.')
                            ->suffixAction(
                                Action::make('reset_overall_total')
                                    ->icon('heroicon-m-arrow-path')
                                    ->tooltip('Reset to auto-calculated value')
                                    ->visible(
                                        fn (Get $get): bool => (bool) $get(
                                            'is_overall_manually_modified'
                                        )
                                    )
                                    ->action(function (
                                        Set $set,
                                        Get $get,
                                        $record
                                    ): void {
                                        // Reset the manual override flag
                                        $set(
                                            'is_overall_manually_modified',
                                            false
                                        );
                                        $set('original_overall_amount', null);

                                        // Trigger recalculation
                                        EnrollmentServiceProvider::updateTotals(
                                            $get,
                                            $set,
                                            $record
                                        );
                                    })
                            )
                            ->label('Overall Total'),
                        TextInput::make('downpayment')
                            ->numeric()
                            ->prefix('₱')
                            ->live(onBlur: true)
                            ->minValue(500)
                            ->default(3500)
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set
                            ): void {
                                // Recalculate balance when downpayment changes
                                $overallTotal =
                                    (float) ($get('overall_total') ?? 0);
                                $downpayment =
                                    (float) ($get('downpayment') ?? 0);
                                $balance = $overallTotal - $downpayment;
                                $set('total_balance', $balance);
                            })
                            ->label('Down Payment'),
                        TextInput::make('total_balance')
                            ->numeric()
                            ->readOnly()
                            ->live()
                            ->prefix('₱')
                            ->label('Balance'),
                    ]),

                // Remarks
                Textarea::make('remarks')
                    ->label('Remarks')
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    private static function getCurrentSchoolYear(): string
    {
        $generalSettingsService = app(GeneralSettingsService::class);

        return $generalSettingsService->getCurrentSchoolYearString();
    }

    private static function getCurrentSemester(): int
    {
        $generalSettingsService = app(GeneralSettingsService::class);

        return $generalSettingsService->getCurrentSemester();
    }
}
