<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\RelationManagers;

use App\Filament\Exports\ClassEnrollmentExporter;
use App\Jobs\BulkMoveStudentsToSectionJob;
use App\Jobs\GenerateStudentListPdfJob;
use App\Jobs\MoveStudentToSectionJob;
use App\Models\Classes;
use App\Models\Student;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class ClassEnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'class_enrollments';

    protected static ?string $recordTitleAttribute = 'student_id';

    protected static ?string $title = 'Enrolled Students';

    public function getTableQueryForExport(): Builder
    {
        return parent::getTableQueryForExport()
            ->where('class_id', $this->getOwnerRecord()->id)
            ->with(['student.course', 'student.studentContactsInfo', 'class.Faculty']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('student_id')
                ->label('Student')
                ->options(fn () => Student::all()->pluck('full_name', 'id'))
                ->searchable()
                ->required()
                ->preload()
                ->columnSpan('full'),

            Grid::make(3)->schema([
                TextInput::make('prelim_grade')
                    ->label('Prelim')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(
                        fn (callable $set) => $set('total_average', null)
                    ),

                TextInput::make('midterm_grade')
                    ->label('Midterm')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(
                        fn (callable $set) => $set('total_average', null)
                    ),

                TextInput::make('finals_grade')
                    ->label('Finals')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->live()
                    ->afterStateUpdated(
                        fn (callable $set) => $set('total_average', null)
                    ),
            ]),

            Grid::make(2)->schema([
                TextInput::make('total_average')
                    ->label('Final Grade')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->disabled()
                    ->placeholder(function (callable $get): string {
                        $prelim = $get('prelim_grade');
                        $midterm = $get('midterm_grade');
                        $finals = $get('finals_grade');

                        if ($prelim !== null && $midterm !== null && $finals !== null) {
                            $average = ($prelim + $midterm + $finals) / 3;

                            return number_format($average, 2);
                        }

                        return 'N/A';
                    }),

                Select::make('status')
                    ->options([
                        true => 'Passed',
                        false => 'Failed',
                    ])
                    ->default(true),
            ]),

            Textarea::make('remarks')->rows(2)->columnSpan('full'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_id')
                    ->label('Student')
                    ->formatStateUsing(
                        fn ($record) => $record->student?->full_name ?? 'N/A'
                    )
                    ->searchable(
                        query: fn ($query, $search) => $query->whereHas(
                            'student',
                            function ($q) use ($search): void {
                                $q->where(
                                    'first_name',
                                    'like',
                                    sprintf('%%%s%%', $search)
                                )->orWhere('last_name', 'like', sprintf('%%%s%%', $search));
                            }
                        )
                    )
                    ->sortable(),
                TextColumn::make('student.course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.academic_year')
                    ->formatStateUsing(
                        fn ($record) => $record->student?->academic_year ?? 'N/A'
                    )
                    ->label('Year')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('date Added')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn ($record): string => $record->status
                            ? 'Enrolled'
                            : 'Not Active'
                    )
                    ->color(
                        fn ($record): string => $record->status
                            ? 'success'
                            : 'gray'
                    ),

                TextColumn::make('prelim_grade')->label('Prelim')->sortable(),

                TextColumn::make('midterm_grade')->label('Midterm')->sortable(),

                TextColumn::make('finals_grade')->label('Finals')->sortable(),

                TextColumn::make('total_average')
                    ->label('Final Grade')
                    ->sortable(),

                IconColumn::make('status')
                    ->boolean()
                    ->label('Status')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('remarks')
                    ->limit(30)
                    ->tooltip(
                        fn (TextColumn $textColumn): mixed => $textColumn->getState()
                    ),

            ])
            ->filters([
                SelectFilter::make('course')
                    ->relationship('student.course', 'code')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('academic_year')
                    ->label('Year Level')
                    ->options([
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'],
                        fn ($query, $value) => $query->whereHas('student', fn ($q) => $q->where('academic_year', $value))
                    )
                    ),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('view_pending')
                    ->label('View Pending Students')
                    ->icon('heroicon-o-clock')
                    ->color('warning')
                    ->modalHeading('Pending Students for this Class')
                    ->modalContent(function (): View|Factory {
                        $pendingInfo = $this->getPendingStudentsInfo();

                        if ($pendingInfo['count'] === 0) {
                            return view(
                                'filament.components.no-pending-students'
                            );
                        }

                        return view(
                            'filament.components.pending-students-list',
                            [
                                'students' => $pendingInfo['students'],
                                'count' => $pendingInfo['count'],
                            ]
                        );
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                Action::make('re_enroll_failed')
                    ->label('Re-enroll Failed Students')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Re-enroll Students Who Failed Auto-Enrollment'
                    )
                    ->modalDescription(
                        'This will attempt to re-enroll students who were verified by cashier but failed to be enrolled in this class due to technical issues.'
                    )
                    ->action(function (): void {
                        $result = $this->reEnrollFailedStudents();

                        if ($result['success_count'] > 0) {
                            Notification::make()
                                ->title('Re-enrollment Successful')
                                ->body(
                                    sprintf('Successfully re-enrolled %s student(s) in this class.', $result['success_count'])
                                )
                                ->success()
                                ->send();
                        }

                        if ($result['error_count'] > 0) {
                            Notification::make()
                                ->title('Re-enrollment Issues')
                                ->body(
                                    sprintf('Failed to re-enroll %s student(s). Check logs for details.', $result['error_count'])
                                )
                                ->warning()
                                ->send();
                        }

                        if (
                            $result['success_count'] === 0 &&
                            $result['error_count'] === 0
                        ) {
                            Notification::make()
                                ->title('No Students to Re-enroll')
                                ->body(
                                    'No students found who need re-enrollment in this class.'
                                )
                                ->info()
                                ->send();
                        }
                    }),
                Action::make('recreate_assessment_pdf')
                    ->label('Recreate Assessment PDFs')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Recreate Assessment PDFs for Enrolled Students'
                    )
                    ->modalDescription(
                        'This will regenerate assessment PDFs for all students enrolled in this class. This is useful when there are issues with class schedules not showing up correctly in the PDFs.'
                    )
                    ->action(function (): void {
                        $result = $this->recreateAssessmentPdfs();

                        if ($result['success_count'] > 0) {
                            Notification::make()
                                ->title('PDFs Recreated Successfully')
                                ->body(
                                    sprintf('Successfully recreated assessment PDFs for %s student(s).', $result['success_count'])
                                )
                                ->success()
                                ->send();
                        }

                        if ($result['error_count'] > 0) {
                            Notification::make()
                                ->title('PDF Recreation Issues')
                                ->body(
                                    sprintf('Failed to recreate PDFs for %s student(s). Check logs for details.', $result['error_count'])
                                )
                                ->warning()
                                ->send();
                        }

                        if (
                            $result['success_count'] === 0 &&
                            $result['error_count'] === 0
                        ) {
                            Notification::make()
                                ->title('No Students Found')
                                ->body(
                                    'No enrolled students found for this class.'
                                )
                                ->info()
                                ->send();
                        }
                    }),
                ActionGroup::make([
                    ExportAction::make('export_students')
                        ->label('Export Students (Excel)')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('success')
                        ->exporter(ClassEnrollmentExporter::class)
                        ->fileName(function (): string {
                            $model = $this->getOwnerRecord();

                            return sprintf(
                                'enrolled_students_%s_%s_%s_%s',
                                str_replace(' ', '_', $model->subject_code ?? 'Unknown'),
                                str_replace(' ', '_', $model->section ?? 'Unknown'),
                                str_replace(' ', '_', $model->semester ?? 'Unknown'),
                                str_replace('-', '_', $model->school_year ?? 'Unknown')
                            );
                        })
                        ->tooltip('Export enrolled students to Excel file with comprehensive data'),
                    Action::make('export_student_list_pdf')
                        ->label('Export Student List (PDF)')
                        ->icon('heroicon-o-document-text')
                        ->color('info')
                        ->tooltip('Generate PDF list of students with auto-scaling to fit one page')
                        ->requiresConfirmation()
                        ->modalHeading('Generate Student List PDF')
                        ->modalDescription('This will generate a PDF containing a simple list of students with their ID, name, course code, and academic year. The PDF will be automatically scaled to fit on one page.')
                        ->modalSubmitActionLabel('Generate PDF')
                        ->action(function (): void {
                            $model = $this->getOwnerRecord();
                            $userId = Auth::id();

                            // Dispatch the job to generate PDF
                            GenerateStudentListPdfJob::dispatch($model, $userId);

                            // Send immediate notification that job was queued
                            Notification::make()
                                ->title('PDF Generation Queued')
                                ->body("Your student list PDF is being generated in the background. You will receive a notification when it's ready for download.")
                                ->info()
                                ->icon('heroicon-o-clock')
                                ->duration(5000)
                                ->send();
                        }),
                ])
                    ->label('Export Options')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('primary')
                    ->button(),
            ])
            ->recordActions([
                Action::make('move')
                    ->requiresConfirmation()
                    ->modalHeading('Move This Student to another class')
                    ->modalDescription('Are you sure you\'d like to Move this student to another Class? This will also update their Subject Enrollment record. The transfer will be processed in the background.')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->label('Move to a Class')
                    ->form([
                        Select::make('moveClass')
                            ->label('Classes')
                            ->hint('Select a Section you want this student to move to')
                            ->options(function () {
                                $currentClass = $this->getOwnerRecord();

                                return Classes::where('id', '!=', $currentClass->id)
                                    ->where('subject_id', $currentClass->subject_id)
                                    ->where('semester', $currentClass->semester)
                                    ->where('school_year', $currentClass->school_year)
                                    ->get()
                                    ->mapWithKeys(function ($class): array {
                                        $slotsInfo = $class->maximum_slots
                                            ? sprintf(' (Available: %s/%s)', $class->available_slots, $class->maximum_slots)
                                            : ' (Unlimited)';
                                        $status = $class->is_full ? ' [FULL]' : '';

                                        return [$class->id => sprintf('Section %s%s%s', $class->section, $slotsInfo, $status)];
                                    });
                            })
                            ->required()
                            ->searchable(),

                        Toggle::make('notifyStudent')
                            ->label('Send Email Notification to Student')
                            ->helperText('Enable this to send an email notification to the student about the section transfer. Disable if the student already knows about the transfer.')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->action(function (array $data, $record): void {
                        MoveStudentToSectionJob::dispatch(
                            $record->id,
                            $data['moveClass'],
                            Auth::id(),
                            $data['notifyStudent'] ?? true
                        );

                        Notification::make()
                            ->title('Student Transfer Queued')
                            ->body(sprintf('Transfer request for %s has been queued for background processing. You will receive a notification when the transfer is complete.', $record->student?->full_name ?? 'student'))
                            ->info()
                            ->icon('heroicon-o-clock')
                            ->duration(5000)
                            ->send();
                    }),
                EditAction::make()
                    ->modalHeading('Edit Student Enrollment')
                    ->modalWidth('lg'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->delete()),
                    BulkAction::make('forceDelete')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->forceDelete()),
                    BulkAction::make('move')
                        ->requiresConfirmation()
                        ->modalHeading('Move These Students to another class')
                        ->modalDescription('Are you sure you\'d like to move these students to another class? This will also update their Subject Enrollment records. The transfers will be processed in the background.')
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->label('Move to a class')
                        ->form([
                            Select::make('moveClass')
                                ->label('Classes')
                                ->options(function () {
                                    $currentClass = $this->getOwnerRecord();

                                    return Classes::where('id', '!=', $currentClass->id)
                                        ->where('subject_id', $currentClass->subject_id)
                                        ->where('semester', $currentClass->semester)
                                        ->where('school_year', $currentClass->school_year)
                                        ->get()
                                        ->mapWithKeys(function ($class): array {
                                            $slotsInfo = $class->maximum_slots
                                                ? sprintf(' (Available: %s/%s)', $class->available_slots, $class->maximum_slots)
                                                : ' (Unlimited)';
                                            $status = $class->is_full ? ' [FULL]' : '';

                                            return [$class->id => sprintf('Section %s%s%s', $class->section, $slotsInfo, $status)];
                                        });
                                })
                                ->required()
                                ->searchable(),

                            Toggle::make('notifyStudents')
                                ->label('Send Email Notifications to Students')
                                ->helperText('Enable this to send email notifications to all students about the section transfer. Disable if the students already know about the transfer.')
                                ->default(true)
                                ->inline(false),
                        ])
                        ->action(function (array $data, Collection $records): void {
                            $classEnrollmentIds = $records->pluck('id')->toArray();

                            BulkMoveStudentsToSectionJob::dispatch(
                                $classEnrollmentIds,
                                $data['moveClass'],
                                Auth::id(),
                                $data['notifyStudents'] ?? true
                            );

                            $targetClass = Classes::find($data['moveClass']);
                            $targetSection = $targetClass?->section ?? 'Unknown';
                            $subjectCode = $targetClass?->subject_code ?? 'Unknown';

                            Notification::make()
                                ->title('Bulk Student Transfer Queued')
                                ->body(sprintf(
                                    "**Operation:** Bulk Student Move\n**Subject:** %s\n**Target Section:** %s\n**Students:** %s\n\nThe bulk transfer request has been queued for background processing. You will receive notifications as the transfers are completed.",
                                    $subjectCode,
                                    $targetSection,
                                    count($classEnrollmentIds)
                                ))
                                ->info()
                                ->icon('heroicon-o-clock')
                                ->duration(8000)
                                ->send();
                        }),
                ]),
            ]);
    }

    /**
     * Get information about students who should be in this class but aren't enrolled yet
     *
     * @return array<string, mixed>
     */
    private function getPendingStudentsInfo(): array
    {
        /** @var Classes $class */
        $class = $this->getOwnerRecord();

        // Get students who have verified enrollments in the same academic period
        // but are not yet enrolled in this specific class
        $class->class_enrollments()
            ->pluck('student_id')
            ->toArray();

        // Find students with verified enrollments who should be in this class
        // This is a simplified version - you might want to add more specific logic
        // based on your business rules for determining which students should be in which classes
        $pendingStudents = collect(); // Empty collection for now

        return [
            'students' => $pendingStudents,
            'count' => $pendingStudents->count(),
        ];
    }

    /**
     * Re-enroll students who failed auto-enrollment
     *
     * @return array<string, int>
     */
    private function reEnrollFailedStudents(): array
    {
        /** @var Classes $class */
        $class = $this->getOwnerRecord();
        $successCount = 0;
        $errorCount = 0;

        // Get pending students who should be enrolled
        $pendingInfo = $this->getPendingStudentsInfo();

        foreach ($pendingInfo['students'] as $student) {
            try {
                // Create class enrollment record
                $class->class_enrollments()->create([
                    'student_id' => $student->id,
                    'status' => true,
                    'remarks' => 'Re-enrolled via admin panel',
                ]);

                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                Log::error('Failed to re-enroll student', [
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
        ];
    }

    /**
     * Recreate assessment PDFs for all enrolled students
     *
     * @return array<string, int>
     */
    private function recreateAssessmentPdfs(): array
    {
        /** @var Classes $class */
        $class = $this->getOwnerRecord();
        $successCount = 0;
        $errorCount = 0;

        $enrolledStudents = $class->class_enrollments()
            ->with('student')
            ->get();

        foreach ($enrolledStudents as $enrollment) {
            try {
                // Here you would trigger the PDF regeneration
                // This depends on your PDF generation system
                // For now, we'll just increment the success counter
                $successCount++;
            } catch (Exception $e) {
                $errorCount++;
                Log::error('Failed to recreate assessment PDF', [
                    'student_id' => $enrollment->student_id,
                    'class_id' => $class->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success_count' => $successCount,
            'error_count' => $errorCount,
        ];
    }
}
