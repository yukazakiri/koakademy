<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Tables;

use App\Enums\AttritionCategory;
use App\Enums\EmploymentStatus;
use App\Enums\ScholarshipType;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentClearance;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Guava\FilamentIconSelectColumn\Tables\Columns\IconSelectColumn;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Mansoor\FilamentVersionable\Table\RevisionsAction;

final class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_id')
                    ->label('ID')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                TextColumn::make('course.code')->sortable(),
                TextColumn::make('gender')->toggleable(
                    isToggledHiddenByDefault: true,
                ),
                TextColumn::make('academic_year')->toggleable(
                    isToggledHiddenByDefault: true,
                ),
                TextColumn::make('email')->toggleable(
                    isToggledHiddenByDefault: true,
                ),
                IconSelectColumn::make('previous_sem_clearance')
                    ->label('Previous Sem Clearance')
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
                        $previous = $student->getPreviousAcademicPeriod();
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
                        $previous = $student->getPreviousAcademicPeriod();
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
                            StudentClearance::query()->create([
                                'student_id' => $student->id,
                                'academic_year' => $previous['academic_year'],
                                'semester' => $previous['semester'],
                                'is_cleared' => $isCleared,
                                'cleared_by' => $isCleared ? (Auth::user()->name ?? 'Admin') : null,
                                'cleared_at' => $isCleared ? now() : null,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Clearance Updated')
                                ->body("{$student->full_name} has been marked as ".($isCleared ? 'Cleared' : 'Not Cleared'))
                                ->send();

                            return;
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
                    ->tooltip(function (Student $student): string {
                        $validation = $student->validateEnrollmentClearance();
                        $previous = $student->getPreviousAcademicPeriod();
                        $previousLabel = "{$previous['academic_year']} Semester {$previous['semester']}";

                        if (! $validation['clearance']) {
                            return "No clearance record for {$previousLabel}";
                        }

                        $status = $validation['allowed'] ? 'Cleared' : 'Not Cleared';

                        return "{$status} for {$previousLabel}";
                    }),

                // Statistical & Reporting Columns (toggleable, hidden by default)
                TextColumn::make('status')
                    ->label('Student Status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                TextColumn::make('ethnicity')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                TextColumn::make('region_of_origin')
                    ->label('Region')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),

                IconColumn::make('is_indigenous_person')
                    ->label('IP')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn (Student $student): string => $student->is_indigenous_person
                        ? 'Indigenous Person: '.($student->indigenous_group ?? 'Yes')
                        : 'Not an Indigenous Person'),

                TextColumn::make('scholarship_type')
                    ->label('Scholarship')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),

                TextColumn::make('employment_status')
                    ->label('Employment')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
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

                TernaryFilter::make('previous_semester_cleared')
                    ->label('Previous Semester Clearance')
                    ->placeholder('All students')
                    ->trueLabel('Cleared for enrollment')
                    ->falseLabel('Not cleared for enrollment')
                    ->queries(
                        true: fn ($query) => $query->whereHas('clearances', function ($q): void {
                            $student = new Student;
                            $previous = $student->getPreviousAcademicPeriod();
                            $q->where('academic_year', $previous['academic_year'])
                                ->where('semester', $previous['semester'])
                                ->where('is_cleared', true);
                        }),
                        false: fn ($query) => $query->whereHas('clearances', function ($q): void {
                            $student = new Student;
                            $previous = $student->getPreviousAcademicPeriod();
                            $q->where('academic_year', $previous['academic_year'])
                                ->where('semester', $previous['semester'])
                                ->where('is_cleared', false);
                        }),
                        blank: fn ($query) => $query,
                    ),

                // Statistical & Reporting Filters
                SelectFilter::make('status')
                    ->label('Student Status')
                    ->options(StudentStatus::class)
                    ->multiple(),

                SelectFilter::make('scholarship_type')
                    ->label('Scholarship Type')
                    ->options(ScholarshipType::class)
                    ->multiple(),

                TernaryFilter::make('is_indigenous_person')
                    ->label('Indigenous Person')
                    ->placeholder('All students')
                    ->trueLabel('Indigenous Persons only')
                    ->falseLabel('Non-Indigenous only'),

                SelectFilter::make('region_of_origin')
                    ->label('Region')
                    ->options([
                        'NCR' => 'NCR',
                        'CAR' => 'CAR',
                        'Region I' => 'Region I',
                        'Region II' => 'Region II',
                        'Region III' => 'Region III',
                        'Region IV-A' => 'Region IV-A',
                        'Region IV-B' => 'Region IV-B',
                        'Region V' => 'Region V',
                        'Region VI' => 'Region VI',
                        'Region VII' => 'Region VII',
                        'Region VIII' => 'Region VIII',
                        'Region IX' => 'Region IX',
                        'Region X' => 'Region X',
                        'Region XI' => 'Region XI',
                        'Region XII' => 'Region XII',
                        'Region XIII' => 'Region XIII',
                        'BARMM' => 'BARMM',
                    ])
                    ->searchable()
                    ->multiple(),

                SelectFilter::make('employment_status')
                    ->label('Employment Status')
                    ->options(EmploymentStatus::class)
                    ->multiple(),

                SelectFilter::make('attrition_category')
                    ->label('Attrition Category')
                    ->options(AttritionCategory::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                RevisionsAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('bulkClearPreviousSemester')
                        ->label('Mark as Cleared (Previous Sem)')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Clear Students')
                        ->modalDescription('Mark all selected students as cleared for the previous semester?')
                        ->form([
                            Checkbox::make('confirm')
                                ->label('I confirm this action')
                                ->required(),
                            Textarea::make('remarks')
                                ->label('Remarks (Optional)')
                                ->placeholder('Enter any notes about this clearance status change'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $tempStudent = new Student;
                            $previous = $tempStudent->getPreviousAcademicPeriod();
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
                                        'remarks' => $data['remarks'] ?? $clearance->remarks,
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

                    BulkAction::make('bulkNotClearPreviousSemester')
                        ->label('Mark as Not Cleared (Previous Sem)')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Bulk Mark as Not Cleared')
                        ->modalDescription('Mark all selected students as NOT cleared for the previous semester?')
                        ->form([
                            Checkbox::make('confirm')
                                ->label('I confirm this action')
                                ->required(),
                            Textarea::make('remarks')
                                ->label('Remarks (Optional)')
                                ->placeholder('Enter any notes about this clearance status change'),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $tempStudent = new Student;
                            $previous = $tempStudent->getPreviousAcademicPeriod();
                            $count = 0;

                            foreach ($records as $student) {
                                $clearance = $student->clearances()
                                    ->where('academic_year', $previous['academic_year'])
                                    ->where('semester', $previous['semester'])
                                    ->first();

                                if (! $clearance) {
                                    StudentClearance::query()->create([
                                        'student_id' => $student->id,
                                        'academic_year' => $previous['academic_year'],
                                        'semester' => $previous['semester'],
                                        'is_cleared' => false,
                                        'remarks' => $data['remarks'] ?? null,
                                    ]);
                                    $count++;
                                } elseif ($clearance->is_cleared) {
                                    $clearance->update([
                                        'is_cleared' => false,
                                        'cleared_by' => null,
                                        'cleared_at' => null,
                                        'remarks' => $data['remarks'] ?? $clearance->remarks,
                                    ]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Bulk Update Completed')
                                ->body("Updated {$count} students to Not Cleared for {$previous['academic_year']} Semester {$previous['semester']}")
                                ->send();
                        }),

                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
