<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Schemas;

use App\Enums\GradeEnum;
use App\Filament\Infolists\Components\TimetableEntry;
use App\Models\Student;
use Exception;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Ysfkaya\FilamentPhoneInput\Infolists\PhoneEntry;
use Ysfkaya\FilamentPhoneInput\PhoneInputNumberType;

final class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Student Details')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'lg' => 3,
                    ])
                    ->columnSpan(4)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'lg' => 3,
                        ])
                            ->columnSpan(['md' => 2])
                            ->schema([
                                TextEntry::make('full_name')
                                    ->label('Full Name')
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->formatStateUsing(fn (string $state): string => mb_strtoupper($state)),
                                TextEntry::make('email')->label('Email'),
                                PhoneEntry::make(
                                    'studentContactsInfo.personal_contact',
                                )
                                    ->displayFormat(PhoneInputNumberType::NATIONAL)
                                    ->label('Phone'),
                                TextEntry::make('birth_date')->label('Birth Date'),
                                ImageEntry::make('DocumentLocation.picture_1x1')
                                    ->label('Student Picture')
                                    ->circular()
                                    ->defaultImageUrl(
                                        'https://via.placeholder.com/150',
                                    )
                                    ->visibility('private'),
                            ]),
                        Section::make('Student info')
                            ->columnSpan(['md' => 1])
                            ->collapsible()
                            ->schema([
                                TextEntry::make('student_id')
                                    ->badge()
                                    ->icon('heroicon-m-user')
                                    ->iconColor('warning')
                                    ->weight(FontWeight::Bold)
                                    ->copyable()
                                    ->copyMessage('Copied!')
                                    ->label('Student ID'),
                                TextEntry::make('created_at')
                                    ->since()
                                    ->label('Created at'),

                                TextEntry::make('updated_at')
                                    ->since()
                                    ->label('Last modified at'),
                                TextEntry::make('Course.code')
                                    ->badge()

                                    ->label('Course'),
                            ]),

                        Fieldset::make('Address Information')->schema([
                            TextEntry::make('personalInfo.current_adress')->label(
                                'Current Address',
                            ),
                            TextEntry::make(
                                'personalInfo.permanent_address',
                            )->label('Permanent Address'),
                        ]),
                        Tabs::make('Additional Details')
                            ->columnSpan(['md' => 3])
                            ->tabs([
                                Tab::make('Academic Information')->schema([
                                    TextEntry::make('academic_year')->label(
                                        'Academic Year',
                                    ),
                                    TextEntry::make('course.code')->label('Course'),
                                ]),
                                Tab::make('Parent Information')->schema([
                                    TextEntry::make(
                                        'studentParentInfo.fathers_name',
                                    )->label('Father Name'),
                                    TextEntry::make(
                                        'studentParentInfo.mothers_name',
                                    )->label('Mother Name'),
                                ]),
                                Tab::make('School Information')->schema([
                                    TextEntry::make(
                                        'studentEducationInfo.elementary_school',
                                    )->label('Elementary School'),
                                    TextEntry::make(
                                        'studentEducationInfo.elementary_graduate_year',
                                    )->label('Elementary School Graduation Year'),
                                    TextEntry::make(
                                        'studentEducationInfo.elementary_school_address',
                                    )->label('Elementary School Address'),
                                    TextEntry::make(
                                        'studentEducationInfo.senior_high_name',
                                    )->label('Senior High School'),
                                    TextEntry::make(
                                        'studentEducationInfo.senior_high_graduate_year',
                                    )->label('Senior High School Graduation Year'),
                                    TextEntry::make(
                                        'studentEducationInfo.senior_high_address',
                                    )->label('Senior High School Address'),
                                ]),
                                Tab::make('Contact Information')->schema([
                                    TextEntry::make(
                                        'studentContactsInfo.personal_contact',
                                    )->label('Phone Number'),
                                    TextEntry::make(
                                        'studentContactsInfo.emergency_contact_name',
                                    )->label('Emergency Contact Name'),
                                    TextEntry::make(
                                        'studentContactsInfo.emergency_contact_phone',
                                    )->label('Emergency Contact Phone'),
                                    TextEntry::make(
                                        'studentContactsInfo.emergency_contact_address',
                                    )->label('Emergency Contact Address'),
                                    TextEntry::make(
                                        'studentContactsInfo.facebook_contact',
                                    )->label('Facebook Contact'),
                                ]),
                                Tab::make('Clearance Status')->schema([
                                    Section::make('Current Semester Clearance')
                                        ->description('Clearance status for the current academic period')
                                        ->schema([
                                            TextEntry::make('clearances')
                                                ->label('Status')
                                                ->state(
                                                    fn (
                                                        $record,
                                                    ): string => $record->hasCurrentClearance()
                                                        ? 'Cleared'
                                                        : 'Not Cleared',
                                                )
                                                ->badge()
                                                ->icon(fn (string $state): string => match ($state) {
                                                    'Cleared' => 'heroicon-o-check-circle',
                                                    'Not Cleared' => 'heroicon-o-x-circle',
                                                    default => 'heroicon-o-question-mark-circle',
                                                })
                                                ->color(
                                                    fn (string $state): string => match (
                                                        $state
                                                    ) {
                                                        'Cleared' => 'success',
                                                        'Not Cleared' => 'danger',
                                                        default => 'gray',
                                                    },
                                                ),
                                            TextEntry::make(
                                                'getCurrentClearanceRecord.cleared_by',
                                            )
                                                ->label('Cleared By')
                                                ->icon('heroicon-o-user')
                                                ->visible(
                                                    fn (
                                                        $record,
                                                    ): bool => (bool) $record->hasCurrentClearance(),
                                                ),
                                            TextEntry::make(
                                                'getCurrentClearanceRecord.cleared_at',
                                            )
                                                ->label('Cleared At')
                                                ->dateTime('F j, Y g:i A')
                                                ->icon('heroicon-o-clock')
                                                ->visible(
                                                    fn (
                                                        $record,
                                                    ): bool => (bool) $record->hasCurrentClearance(),
                                                ),
                                            TextEntry::make(
                                                'getCurrentClearanceRecord.remarks',
                                            )
                                                ->label('Remarks')
                                                ->markdown()
                                                ->columnSpanFull()
                                                ->visible(
                                                    fn (
                                                        $record,
                                                    ): bool => (bool) $record->getCurrentClearanceModel()?->remarks,
                                                ),
                                            TextEntry::make(
                                                'getCurrentClearanceRecord.academic_year',
                                            )
                                                ->label('Academic Year')
                                                ->badge()
                                                ->color('info')
                                                ->visible(
                                                    fn (
                                                        $record,
                                                    ): bool => (bool) $record->getCurrentClearanceModel(),
                                                ),
                                            TextEntry::make(
                                                'getCurrentClearanceRecord.formatted_semester',
                                            )
                                                ->label('Semester')
                                                ->badge()
                                                ->color('info')
                                                ->visible(
                                                    fn (
                                                        $record,
                                                    ): bool => (bool) $record->getCurrentClearanceModel(),
                                                ),
                                        ])
                                        ->columns(2),

                                    Section::make('Previous Semester Clearance')
                                        ->description('Required clearance check for enrollment eligibility')
                                        ->schema([
                                            TextEntry::make('previous_clearance_status')
                                                ->label('Status')
                                                ->state(function ($record): string {
                                                    $validation = $record->validateEnrollmentClearance();
                                                    if (! $validation['clearance']) {
                                                        return 'No Record';
                                                    }

                                                    return $validation['allowed'] ? 'Cleared' : 'Not Cleared';
                                                })
                                                ->badge()
                                                ->icon(fn (string $state): string => match ($state) {
                                                    'Cleared' => 'heroicon-o-check-circle',
                                                    'Not Cleared' => 'heroicon-o-x-circle',
                                                    'No Record' => 'heroicon-o-question-mark-circle',
                                                    default => 'heroicon-o-question-mark-circle',
                                                })
                                                ->color(fn (string $state): string => match ($state) {
                                                    'Cleared' => 'success',
                                                    'Not Cleared' => 'danger',
                                                    'No Record' => 'gray',
                                                    default => 'gray',
                                                }),
                                            TextEntry::make('previous_semester_info')
                                                ->label('Academic Period')
                                                ->state(function ($record): string {
                                                    $previous = $record->getPreviousAcademicPeriod();

                                                    return "{$previous['academic_year']} - Semester {$previous['semester']}";
                                                })
                                                ->badge()
                                                ->color('warning')
                                                ->icon('heroicon-o-calendar'),
                                            TextEntry::make('enrollment_eligibility')
                                                ->label('Enrollment Eligibility')
                                                ->state(function ($record): string {
                                                    $validation = $record->validateEnrollmentClearance();

                                                    return $validation['message'];
                                                })
                                                ->columnSpanFull()
                                                ->color(function ($record): string {
                                                    $validation = $record->validateEnrollmentClearance();

                                                    return $validation['allowed'] ? 'success' : 'danger';
                                                }),
                                        ])
                                        ->columns(2)
                                        ->collapsible(),

                                    RepeatableEntry::make('clearances')
                                        ->label('Clearance History')
                                        ->schema([
                                            TextEntry::make('academic_year')
                                                ->label('Academic Year')
                                                ->badge()
                                                ->color('primary'),
                                            TextEntry::make('formatted_semester')
                                                ->label('Semester')
                                                ->badge(),
                                            TextEntry::make('is_cleared')
                                                ->label('Status')
                                                ->state(fn ($record): string => $record->is_cleared ? 'Cleared' : 'Not Cleared')
                                                ->badge()
                                                ->icon(fn ($record): string => $record->is_cleared ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                                                ->color(fn ($record): string => $record->is_cleared ? 'success' : 'danger'),
                                            TextEntry::make('cleared_by')
                                                ->label('Cleared By')
                                                ->placeholder('N/A')
                                                ->icon('heroicon-o-user'),
                                            TextEntry::make('cleared_at')
                                                ->label('Cleared At')
                                                ->dateTime('M j, Y g:i A')
                                                ->placeholder('N/A')
                                                ->icon('heroicon-o-clock'),
                                            TextEntry::make('remarks')
                                                ->label('Remarks')
                                                ->placeholder('No remarks')
                                                ->columnSpanFull()
                                                ->markdown(),
                                        ])
                                        ->columns(3)
                                        ->columnSpanFull()
                                        ->contained(false),
                                ]),
                                Tab::make('Tuition Information')
                                    ->schema([
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_total_tuition',
                                        )
                                            ->label('Total Tuition')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_total_lectures',
                                        )
                                            ->label('Lecture Fees')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_total_laboratory',
                                        )
                                            ->label('Laboratory Fees')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_total_miscelaneous_fees',
                                        )
                                            ->label('Miscellaneous Fees')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_overall_tuition',
                                        )
                                            ->label('Overall Tuition')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_downpayment',
                                        )
                                            ->label('Downpayment')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_total_balance',
                                        )
                                            ->label('Balance')
                                            ->placeholder('No tuition record found')
                                            ->badge()
                                            ->color(function ($record): string {
                                                $tuition = $record->getCurrentTuitionModel();
                                                if (! $tuition) {
                                                    return 'gray';
                                                }

                                                return $tuition->total_balance <= 0
                                                    ? 'success'
                                                    : 'danger';
                                            }),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_discount',
                                        )
                                            ->label('Discount')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.payment_status',
                                        )
                                            ->label('Payment Status')
                                            ->placeholder('No tuition record found')
                                            ->badge()
                                            ->color(function ($record): string {
                                                $tuition = $record->getCurrentTuitionModel();
                                                if (! $tuition) {
                                                    return 'gray';
                                                }

                                                return $tuition->total_balance <= 0
                                                    ? 'success'
                                                    : 'warning';
                                            }),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.formatted_semester',
                                        )
                                            ->label('Semester')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                        TextEntry::make(
                                            'getCurrentTuitionRecord.academic_year',
                                        )
                                            ->label('Academic Year')
                                            ->placeholder(
                                                'No tuition record found',
                                            ),
                                    ])
                                    ->columns(2),
                                Tab::make('Current Enrolled Subjects')->schema(
                                    [
                                        RepeatableEntry::make(
                                            'subjectEnrolled',
                                        )
                                            ->label('Enrolled Subjects')
                                            ->schema([
                                                TextEntry::make(
                                                    'subject.code',
                                                )->label('Subject Code'),
                                                TextEntry::make(
                                                    'subject.title',
                                                )->label('Subject Title'),
                                                TextEntry::make(
                                                    'subject.units',
                                                )->label('Units'),
                                                TextEntry::make(
                                                    'class.section',
                                                )->label('Section'),
                                            ])
                                            ->columns(4)
                                            ->columnSpan(2),
                                    ],
                                ),

                                Tab::make('Document Location')
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                        'lg' => 3,
                                    ])
                                    ->schema([
                                        ImageEntry::make(
                                            'DocumentLocation.transcript_records',
                                        )
                                            ->label('Transcript Records')

                                            ->defaultImageUrl(
                                                'https://via.placeholder.com/150',
                                            )
                                            ->visibility('private'),
                                        ImageEntry::make(
                                            'DocumentLocation.transfer_credentials',
                                        )
                                            ->label('Transfer Credentials')

                                            ->defaultImageUrl(
                                                'https://via.placeholder.com/150',
                                            )
                                            ->visibility('private'),
                                        ImageEntry::make(
                                            'DocumentLocation.good_moral_cert',
                                        )
                                            ->label('Good Moral Certificate')

                                            ->defaultImageUrl(
                                                'https://via.placeholder.com/150',
                                            )
                                            ->visibility('private'),
                                        ImageEntry::make(
                                            'DocumentLocation.form_137',
                                        )
                                            ->label('Form 137')

                                            ->defaultImageUrl(
                                                'https://via.placeholder.com/150',
                                            )
                                            ->visibility('private'),
                                        ImageEntry::make(
                                            'DocumentLocation.form_138',
                                        )
                                            ->label('Form 138')

                                            ->defaultImageUrl(
                                                'https://via.placeholder.com/150',
                                            )
                                            ->visibility('private'),
                                        ImageEntry::make(
                                            'DocumentLocation.birth_certificate',
                                        )
                                            ->label('Birth Certificate')

                                            ->defaultImageUrl(
                                                'https://via.placeholder.com/150',
                                            )
                                            ->visibility('private'),
                                    ]),
                            ]),

                    ]),
                Section::make('Class Timetable')
                    ->columnSpanFull()
                    ->visible(fn (Student $record): bool => $record->getCurrentClasses()->isNotEmpty())
                    ->schema([
                        TimetableEntry::make('timetable')
                            ->showHeader(true)
                            ->showLegend(true)
                            ->allowToggle(true),
                    ])
                    ->headerActions([
                        \Filament\Actions\Action::make('export_pdf')
                            ->label('Export to PDF')
                            ->icon('heroicon-o-document-arrow-down')
                            ->color('primary')
                            ->action(function (Student $record): void {
                                try {
                                    // Dispatch the job to generate PDF in the background
                                    \App\Jobs\GenerateStudentTimetablePdfJob::dispatch($record);

                                    \Filament\Notifications\Notification::make()
                                        ->title('PDF Generation Started')
                                        ->body('The timetable PDF is being generated in the background. You will be notified when it is ready.')
                                        ->info()
                                        ->send();

                                } catch (Exception $e) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('PDF Generation Failed')
                                        ->body('There was an error starting the PDF generation: '.$e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }),
                    ]),
                Section::make('Checklist')
                    ->columnSpanFull()
                    ->schema([
                        Tabs::make('Checklist')
                            ->tabs(function (Student $student): array {
                                $tabs = [];
                                $groupedSubjects = $student->subjects()->orderBy('academic_year')->orderBy('semester')->get()->groupBy('academic_year');
                                $subjectEnrolled = $student->subjectEnrolled->keyBy('subject_id');

                                $academicYears = $groupedSubjects->keys()->sort()->values();
                                $yearLevelMap = $academicYears->mapWithKeys(fn ($year, $index): array => [$year => $index + 1]);

                                foreach ($groupedSubjects as $year => $subjectsForYear) {
                                    $yearLevel = $yearLevelMap[$year];
                                    $yearName = match ($yearLevel) {
                                        1 => '1st Year',
                                        2 => '2nd Year',
                                        3 => '3rd Year',
                                        4 => '4th Year',
                                        default => $yearLevel.'th Year',
                                    };

                                    $tabs[] = Tab::make($yearName)
                                        ->schema([
                                            Tabs::make('Semesters')
                                                ->tabs(function () use ($subjectsForYear, $subjectEnrolled): array {
                                                    $semesterTabs = [];
                                                    $semesters = $subjectsForYear->groupBy('semester');

                                                    foreach ($semesters as $semester => $subjects) {
                                                        $semesterName = match ((int) $semester) {
                                                            1 => '1st Semester',
                                                            2 => '2nd Semester',
                                                            3 => 'Summer',
                                                            default => 'Semester '.$semester,
                                                        };
                                                        $semesterTabs[] = Tab::make($semesterName)
                                                            ->schema([
                                                                TextEntry::make('subject_table')
                                                                    ->label('')
                                                                    ->html()
                                                                    ->state(function () use ($subjects, $subjectEnrolled): string {
                                                                        $html = '<table class="w-full text-sm text-left rtl:text-right fi-table">';
                                                                        $html .= '<thead class="fi-table-header">';
                                                                        $html .= '<tr>';
                                                                        $html .= '<th class="fi-table-header-cell p-2">Code</th>';
                                                                        $html .= '<th class="fi-table-header-cell p-2">Title</th>';
                                                                        $html .= '<th class="fi-table-header-cell p-2 text-right">Units</th>';
                                                                        $html .= '<th class="fi-table-header-cell p-2">Status</th>';
                                                                        $html .= '<th class="fi-table-header-cell p-2">Grade</th>';
                                                                        $html .= '</tr>';
                                                                        $html .= '</thead>';
                                                                        $html .= '<tbody class="fi-table-body">';

                                                                        foreach ($subjects as $subject) {
                                                                            $enrolledSubject = $subjectEnrolled->get($subject->id);
                                                                            $status = 'Not Completed';
                                                                            $statusColor = 'danger';
                                                                            $grade = '-';
                                                                            $gradeColor = 'gray';

                                                                            if ($enrolledSubject) {
                                                                                if ($enrolledSubject->grade) {
                                                                                    $status = 'Completed';
                                                                                    $statusColor = 'success';
                                                                                    $grade = number_format($enrolledSubject->grade, 2);
                                                                                    $gradeColor = GradeEnum::fromGrade($enrolledSubject->grade)->getColor();
                                                                                } else {
                                                                                    $status = 'In Progress';
                                                                                    $statusColor = 'warning';
                                                                                }
                                                                            }

                                                                            $statusBadge = view('filament::components.badge', ['color' => $statusColor, 'slot' => $status])->render();
                                                                            $gradeBadge = view('filament::components.badge', ['color' => $gradeColor, 'slot' => $grade])->render();

                                                                            $html .= '<tr class="fi-table-row">';
                                                                            $html .= '<td class="fi-table-cell p-2">'.e($subject->code).'</td>';
                                                                            $html .= '<td class="fi-table-cell p-2">'.e($subject->title).'</td>';
                                                                            $html .= '<td class="fi-table-cell p-2 text-right">'.e($subject->units).'</td>';
                                                                            $html .= '<td class="fi-table-cell p-2">'.$statusBadge.'</td>';
                                                                            $html .= '<td class="fi-table-cell p-2">'.$gradeBadge.'</td>';
                                                                            $html .= '</tr>';
                                                                        }

                                                                        return $html.'</tbody></table>';
                                                                    }),
                                                            ]);
                                                    }

                                                    return $semesterTabs;
                                                }),
                                        ]);
                                }

                                return $tabs;
                            }),
                    ]),

            ]);
    }
}
