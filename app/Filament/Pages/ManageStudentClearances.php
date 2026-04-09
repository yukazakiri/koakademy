<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Student;
use App\Models\StudentClearance;
use App\Services\GeneralSettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Guava\FilamentIconSelectColumn\Tables\Columns\IconSelectColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

final class ManageStudentClearances extends Page implements HasTable
{
    use InteractsWithTable;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected string $view = 'filament.pages.manage-student-clearances';

    protected static string|UnitEnum|null $navigationGroup = 'Academics';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Manage Student Clearances';

    protected static ?string $navigationLabel = 'Clearances';

    public function table(Table $table): Table
    {
        $settingsService = app(GeneralSettingsService::class);
        $currentYear = $settingsService->getCurrentSchoolYearString();
        $currentSemester = $settingsService->getCurrentSemester();

        // Get previous academic period for display
        $tempStudent = new Student;
        $previous = $tempStudent->getPreviousAcademicPeriod($currentYear, $currentSemester);
        $previousLabel = "{$previous['academic_year']} Semester {$previous['semester']}";

        return $table
            ->query(Student::query()->with(['clearances', 'course']))
            ->heading("Student Clearances for {$previousLabel}")
            ->description('Manage clearance statuses for all students. Students must be cleared from the previous semester before they can enroll.')
            ->columns([
                TextColumn::make('student_id')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('course.code')
                    ->label('Course')
                    ->sortable()
                    ->badge(),

                TextColumn::make('academic_year')
                    ->label('Year Level')
                    ->badge()
                    ->sortable(),

                IconSelectColumn::make('clearance_status')
                    ->label('Previous Sem Status')
                    ->options([
                        'cleared' => 'Cleared',
                        'not_cleared' => 'Not Cleared',
                        'no_record' => 'No Record',
                    ])
                    ->icons([
                        'cleared' => 'heroicon-o-check-circle',
                        'not_cleared' => 'heroicon-o-x-circle',
                        'no_record' => 'heroicon-o-question-mark-circle',
                    ])
                    ->colors([
                        'cleared' => 'success',
                        'not_cleared' => 'danger',
                        'no_record' => 'gray',
                    ])
                    ->getStateUsing(function (Student $student): string {
                        $previous = $this->getPreviousAcademicPeriod();
                        $clearance = $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        if (! $clearance) {
                            return 'no_record';
                        }

                        return $clearance->is_cleared ? 'cleared' : 'not_cleared';
                    })
                    ->updateStateUsing(function (Student $student, string $state): void {
                        $previous = $this->getPreviousAcademicPeriod();
                        $clearance = $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        if ($state === 'no_record') {
                            if ($clearance) {
                                $clearance->delete();
                                Notification::make()
                                    ->warning()
                                    ->title('Clearance Record Deleted')
                                    ->body("Clearance record for {$student->full_name} has been deleted.")
                                    ->send();
                            }

                            return;
                        }

                        $isCleared = $state === 'cleared';

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
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Clearance Updated')
                            ->body("{$student->full_name} has been marked as ".($isCleared ? 'Cleared' : 'Not Cleared'))
                            ->send();
                    })
                    // ->selectablePlaceholder(false)
                    // ->closeOnSelection()
                    ->sortable(),

                TextColumn::make('cleared_by')
                    ->label('Cleared By')
                    ->getStateUsing(function (Student $student): string {
                        $previous = $this->getPreviousAcademicPeriod();
                        $clearance = $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        return $clearance?->cleared_by ?? 'N/A';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cleared_at')
                    ->label('Cleared At')
                    ->getStateUsing(function (Student $student): string {
                        $previous = $this->getPreviousAcademicPeriod();
                        $clearance = $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        return $clearance?->cleared_at?->format('M j, Y g:i A') ?? 'N/A';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('enrollment_eligibility')
                    ->label('Can Enroll?')
                    ->getStateUsing(function (Student $student): string {
                        $validation = $student->validateEnrollmentClearance();

                        return $validation['allowed'] ? 'Yes' : 'No';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Yes' ? 'success' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('course_id')
                    ->label('Course')
                    ->relationship('course', 'code')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('academic_year')
                    ->label('Year Level')
                    ->options([
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                        '5' => 'Graduate',
                    ]),

                TernaryFilter::make('is_cleared')
                    ->label('Clearance Status')
                    ->placeholder('All Students')
                    ->trueLabel('Cleared')
                    ->falseLabel('Not Cleared')
                    ->query(function (Builder $query, ?array $state): Builder {
                        if ($state === null) {
                            return $query;
                        }

                        $previous = $this->getPreviousAcademicPeriod();
                        $isCleared = $state === 'true';

                        return $query->whereHas('clearances', function ($q) use ($previous, $isCleared): void {
                            $q->where('academic_year', $previous['academic_year'])
                                ->where('semester', $previous['semester'])
                                ->where('is_cleared', $isCleared);
                        });
                    }),

                TernaryFilter::make('has_clearance_record')
                    ->label('Has Clearance Record')
                    ->placeholder('All Students')
                    ->trueLabel('Has Record')
                    ->falseLabel('No Record')
                    ->query(function (Builder $query, ?array $state): Builder {
                        if ($state === null) {
                            return $query;
                        }

                        $previous = $this->getPreviousAcademicPeriod();

                        if ($state === 'true') {
                            return $query->whereHas('clearances', function ($q) use ($previous): void {
                                $q->where('academic_year', $previous['academic_year'])
                                    ->where('semester', $previous['semester']);
                            });
                        }

                        return $query->whereDoesntHave('clearances', function ($q) use ($previous): void {
                            $q->where('academic_year', $previous['academic_year'])
                                ->where('semester', $previous['semester']);
                        });
                    }),
            ])
            ->actions([
                Action::make('toggleClearance')
                    ->label(function (Student $student): string {
                        $previous = $this->getPreviousAcademicPeriod();

                        return $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->where('is_cleared', true)
                            ->exists() ? 'Mark as Not Cleared' : 'Mark as Cleared';
                    })
                    ->icon(function (Student $student): string {
                        $previous = $this->getPreviousAcademicPeriod();

                        return $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->where('is_cleared', true)
                            ->exists() ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle';
                    })
                    ->color(function (Student $student): string {
                        $previous = $this->getPreviousAcademicPeriod();

                        return $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->where('is_cleared', true)
                            ->exists() ? 'danger' : 'success';
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn (Student $student): string => 'Update Clearance for '.$student->full_name)
                    ->form([
                        Textarea::make('remarks')
                            ->label('Remarks (Optional)')
                            ->placeholder('Enter any notes about this clearance status change'),
                    ])
                    ->action(function (Student $student, array $data): void {
                        $previous = $this->getPreviousAcademicPeriod();
                        $clearance = $student->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        if (! $clearance) {
                            $clearance = StudentClearance::query()->create([
                                'student_id' => $student->id,
                                'academic_year' => $previous['academic_year'],
                                'semester' => $previous['semester'],
                                'is_cleared' => false,
                            ]);
                        }

                        $newStatus = ! $clearance->is_cleared;

                        $clearance->update([
                            'is_cleared' => $newStatus,
                            'cleared_by' => $newStatus ? (Auth::user()->name ?? 'Admin') : null,
                            'cleared_at' => $newStatus ? now() : null,
                            'remarks' => $data['remarks'] ?? $clearance->remarks,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Clearance Updated')
                            ->body("{$student->full_name} has been marked as ".($newStatus ? 'Cleared' : 'Not Cleared'))
                            ->send();
                    }),

                Action::make('viewHistory')
                    ->label('View History')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->url(fn (Student $student): string => route('filament.admin.resources.students.view', ['record' => $student->id])),
            ])
            ->bulkActions([
                BulkAction::make('bulkClear')
                    ->label('Mark as Cleared')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Bulk Clear Students')
                    ->modalDescription('Mark all selected students as cleared for the previous semester?')
                    ->form([
                        Checkbox::make('confirm')
                            ->label('I confirm this action')
                            ->required(),
                    ])
                    ->action(function (Collection $records): void {
                        $previous = $this->getPreviousAcademicPeriod();
                        $clearedCount = 0;

                        foreach ($records as $student) {
                            $clearance = $student->clearances()
                                ->where('academic_year', $previous['academic_year'])
                                ->where('semester', $previous['semester'])
                                ->first();

                            if (! $clearance) {
                                $clearance = StudentClearance::query()->create([
                                    'student_id' => $student->id,
                                    'academic_year' => $previous['academic_year'],
                                    'semester' => $previous['semester'],
                                    'is_cleared' => false,
                                ]);
                            }

                            if (! $clearance->is_cleared) {
                                $clearance->update([
                                    'is_cleared' => true,
                                    'cleared_by' => Auth::user()->name ?? 'Admin',
                                    'cleared_at' => now(),
                                ]);
                                $clearedCount++;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Bulk Clear Completed')
                            ->body("Successfully cleared {$clearedCount} students")
                            ->send();
                    }),

                BulkAction::make('bulkNotClear')
                    ->label('Mark as Not Cleared')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Bulk Mark as Not Cleared')
                    ->modalDescription('Mark all selected students as NOT cleared for the previous semester?')
                    ->form([
                        Checkbox::make('confirm')
                            ->label('I confirm this action')
                            ->required(),
                    ])
                    ->action(function (Collection $records): void {
                        $previous = $this->getPreviousAcademicPeriod();
                        $count = 0;

                        foreach ($records as $student) {
                            $clearance = $student->clearances()
                                ->where('academic_year', $previous['academic_year'])
                                ->where('semester', $previous['semester'])
                                ->first();

                            if (! $clearance) {
                                $clearance = StudentClearance::query()->create([
                                    'student_id' => $student->id,
                                    'academic_year' => $previous['academic_year'],
                                    'semester' => $previous['semester'],
                                    'is_cleared' => false,
                                ]);
                                $count++;
                            } elseif ($clearance->is_cleared) {
                                $clearance->update([
                                    'is_cleared' => false,
                                    'cleared_by' => null,
                                    'cleared_at' => null,
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('Bulk Update Completed')
                            ->body("Updated {$count} students to Not Cleared")
                            ->send();
                    }),
            ])
            ->defaultSort('student_id', 'asc');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkClearAll')
                ->label('Bulk Clear All Students')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Bulk Clear All Students')
                ->modalDescription('This will mark ALL students as cleared for the previous semester. Are you sure?')
                ->modalSubmitActionLabel('Clear All Students')
                ->action(function (): void {
                    $settingsService = app(GeneralSettingsService::class);
                    $currentYear = $settingsService->getCurrentSchoolYearString();
                    $currentSemester = $settingsService->getCurrentSemester();

                    $students = Student::all();
                    $clearedCount = 0;

                    foreach ($students as $student) {
                        $previous = $student->getPreviousAcademicPeriod($currentYear, $currentSemester);
                        $clearance = StudentClearance::query()
                            ->where('student_id', $student->id)
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        if (! $clearance) {
                            $clearance = StudentClearance::query()->create([
                                'student_id' => $student->id,
                                'academic_year' => $previous['academic_year'],
                                'semester' => $previous['semester'],
                                'is_cleared' => false,
                            ]);
                        }

                        if (! $clearance->is_cleared) {
                            $clearance->update([
                                'is_cleared' => true,
                                'cleared_by' => Auth::user()->name ?? 'Admin',
                                'cleared_at' => now(),
                            ]);
                            $clearedCount++;
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title('Bulk Clear Completed')
                        ->body("Successfully cleared {$clearedCount} students for {$previous['academic_year']} Semester {$previous['semester']}")
                        ->send();
                }),

            Action::make('createClearances')
                ->label('Create Missing Clearance Records')
                ->icon('heroicon-o-plus-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Create Missing Clearance Records')
                ->modalDescription('This will create clearance records for the previous semester for students who don\'t have one yet. All new records will be marked as "Not Cleared".')
                ->modalSubmitActionLabel('Create Records')
                ->action(function (): void {
                    $settingsService = app(GeneralSettingsService::class);
                    $currentYear = $settingsService->getCurrentSchoolYearString();
                    $currentSemester = $settingsService->getCurrentSemester();

                    $students = Student::all();
                    $createdCount = 0;

                    foreach ($students as $student) {
                        $previous = $student->getPreviousAcademicPeriod($currentYear, $currentSemester);
                        $exists = StudentClearance::query()
                            ->where('student_id', $student->id)
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->exists();

                        if (! $exists) {
                            StudentClearance::query()->create([
                                'student_id' => $student->id,
                                'academic_year' => $previous['academic_year'],
                                'semester' => $previous['semester'],
                                'is_cleared' => false,
                            ]);
                            $createdCount++;
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title('Clearance Records Created')
                        ->body("Created {$createdCount} new clearance records for {$previous['academic_year']} Semester {$previous['semester']}")
                        ->send();
                }),
        ];
    }

    private function getPreviousAcademicPeriod(): array
    {
        $settingsService = app(GeneralSettingsService::class);
        $currentYear = $settingsService->getCurrentSchoolYearString();
        $currentSemester = $settingsService->getCurrentSemester();

        $tempStudent = new Student;

        return $tempStudent->getPreviousAcademicPeriod($currentYear, $currentSemester);
    }
}
