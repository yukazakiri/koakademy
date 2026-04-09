<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Tables;

use App\Filament\Exports\StudentEnrollmentExporter;
use App\Models\Course;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use App\Services\EnrollmentService;
use App\Services\GeneralSettingsService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class StudentEnrollmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student_id')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.last_name')
                    ->label('Last Name')
                    ->sortable(),
                TextColumn::make('student.first_name')
                    ->label('First Name')
                    ->sortable(),
                TextColumn::make('student.course.code')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('academic_year')
                    ->label('Year Level')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Date Enrolled')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('created_at', 'desc')
            ->deferLoading()
            ->modifyQueryUsing(fn (Builder $query) => $query->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]))
            ->searchPlaceholder('Search by student name or ID')
            ->filters([
                TrashedFilter::make()->default('with'),
                Filter::make('current_semester')
                    ->label('Current Semester Only')
                    ->default(true)
                    ->query(function (Builder $query): Builder {
                        $settings = app(GeneralSettingsService::class);

                        return $query->where('school_year', $settings->getCurrentSchoolYearString())
                            ->where('semester', $settings->getCurrentSemester());
                    }),
                SelectFilter::make('academic_year')
                    ->label('Year Level')
                    ->options([
                        1 => '1st Year',
                        2 => '2nd Year',
                        3 => '3rd Year',
                        4 => '4th Year',
                    ]),
                SelectFilter::make('status')
                    ->label('Enrollment Status')
                    ->options(function (): array {
                        $labels = app(EnrollmentPipelineService::class)->getStatusLabels();

                        return $labels;
                    }),
                Filter::make('course')
                    ->form([
                        Select::make('course_id')
                            ->label('Course')
                            ->options(Course::query()->pluck('code', 'id'))
                            ->searchable()
                            ->multiple()
                            ->preload(),
                    ])
                    ->query(function (Builder $builder, array $data): Builder {
                        if (
                            ! isset($data['course_id']) ||
                            empty($data['course_id'])
                        ) {
                            return $builder;
                        }

                        return $builder->whereExists(function ($query) use (
                            $data
                        ): void {
                            $query
                                ->select(DB::raw(1))
                                ->from('students')
                                ->whereRaw(
                                    'CAST(student_enrollment.student_id AS BIGINT) = students.id'
                                )
                                ->whereIn(
                                    'students.course_id',
                                    $data['course_id']
                                )
                                ->whereNull('students.deleted_at');
                        });
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('quickEnrollNoReceipt')
                    ->label('⚡ Quick Enroll')
                    ->icon('heroicon-o-bolt')
                    ->color('danger')
                    ->modalHeading('⚡ Quick Enroll Without Receipt')
                    ->modalDescription(
                        'EMERGENCY USE ONLY: Skip all verification steps and directly enroll this student without receipt.'
                    )
                    ->form([
                        Placeholder::make('emergency_warning')
                            ->label('')
                            ->content('🚨 EMERGENCY ENROLLMENT - This bypasses all normal verification workflows.')
                            ->columnSpanFull(),
                        Textarea::make('remarks')
                            ->label('Justification & Verification Details')
                            ->placeholder('Explain why normal verification is being bypassed and how payment was verified...')
                            ->required()
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                        Toggle::make('confirm_emergency')
                            ->label('I confirm this is an emergency enrollment with proper authorization')
                            ->required()
                            ->accepted()
                            ->columnSpanFull(),
                        Toggle::make('confirm_payment')
                            ->label('I confirm payment has been verified through alternative means')
                            ->required()
                            ->accepted()
                            ->columnSpanFull(),
                    ])
                    ->visible(function (StudentEnrollment $record): bool {
                        $pipeline = app(EnrollmentPipelineService::class);

                        return $pipeline->isPending($record->status) && Auth::user()->hasRole('super_admin');
                    })
                    ->requiresConfirmation()
                    ->action(function (StudentEnrollment $record, array $data, EnrollmentService $enrollmentService): void {
                        try {
                            $pipeline = app(EnrollmentPipelineService::class);

                            // Mark as verified by head dept
                            $record->status = $pipeline->getDepartmentVerifiedStatus();
                            $record->save();

                            // Then use no-receipt verification
                            $success = $enrollmentService->verifyByCashierWithoutReceipt(
                                $record,
                                ['remarks' => '⚡ QUICK ENROLL (Table Action): '.$data['remarks']]
                            );

                            if ($success) {
                                Notification::make()
                                    ->success()
                                    ->title('⚡ Quick Enrollment Successful')
                                    ->body('Student enrolled via emergency quick enroll.')
                                    ->send();

                                Log::warning('Emergency Quick Enroll used (Table Action)', [
                                    'enrollment_id' => $record->id,
                                    'student_id' => $record->student_id,
                                    'admin_id' => Auth::id(),
                                    'remarks' => $data['remarks'],
                                ]);
                            }
                        } catch (Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Quick Enrollment Failed')
                                ->body('Error: '.$e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->label('Export Selected')
                        ->exporter(StudentEnrollmentExporter::class),
                    BulkAction::make('bulkQuickEnroll')
                        ->label('⚡ Bulk Quick Enroll')
                        ->icon('heroicon-o-bolt')
                        ->color('danger')
                        ->modalHeading('⚡ Bulk Quick Enroll Without Receipt')
                        ->modalDescription('EMERGENCY USE ONLY: Quick enroll multiple students at once without receipts.')
                        ->form([
                            Placeholder::make('warning')
                                ->label('')
                                ->content('🚨 **EMERGENCY BULK ENROLLMENT** - This will quick enroll all selected students without receipts. This bypasses ALL normal verification workflows.')
                                ->columnSpanFull(),
                            Textarea::make('remarks')
                                ->label('Bulk Enrollment Justification')
                                ->placeholder('Explain why bulk quick enrollment is necessary and how payments were verified...')
                                ->helperText('This justification will be applied to all selected enrollments.')
                                ->required()
                                ->rows(5)
                                ->maxLength(2000)
                                ->columnSpanFull(),
                            Toggle::make('confirm_emergency')
                                ->label('I confirm this is an emergency bulk enrollment with proper authorization')
                                ->required()
                                ->accepted()
                                ->columnSpanFull(),
                            Toggle::make('confirm_payment')
                                ->label('I confirm all payments have been verified through alternative means')
                                ->required()
                                ->accepted()
                                ->columnSpanFull(),
                            Toggle::make('confirm_understanding')
                                ->label('I understand I am enrolling multiple students at once and take full responsibility')
                                ->required()
                                ->accepted()
                                ->columnSpanFull(),
                        ])
                        ->visible(fn () => Auth::user()->hasRole('super_admin'))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function ($records, array $data): void {
                            $enrollmentService = app(EnrollmentService::class);
                            $totalRecords = count($records);
                            $successCount = 0;
                            $failedCount = 0;
                            $errors = [];

                            // Show initial notification with count
                            Notification::make()
                                ->info()
                                ->title('Processing Bulk Quick Enrollment')
                                ->body("Processing {$totalRecords} student(s)...")
                                ->send();

                            foreach ($records as $record) {
                                $pipeline = app(EnrollmentPipelineService::class);

                                // Only process pending enrollments
                                if (! $pipeline->isPending($record->status)) {
                                    $failedCount++;
                                    $errors[] = "Student ID {$record->student_id}: Not in pending status";

                                    continue;
                                }

                                try {
                                    DB::beginTransaction();

                                    // Mark as verified by head dept
                                    $record->status = $pipeline->getDepartmentVerifiedStatus();
                                    $record->save();

                                    // Then use no-receipt verification
                                    $success = $enrollmentService->verifyByCashierWithoutReceipt(
                                        $record,
                                        ['remarks' => '⚡ BULK QUICK ENROLL: '.$data['remarks']]
                                    );

                                    if ($success) {
                                        $successCount++;
                                        DB::commit();
                                    } else {
                                        $failedCount++;
                                        $errors[] = "Student ID {$record->student_id}: Verification failed";
                                        DB::rollBack();
                                    }
                                } catch (Exception $e) {
                                    $failedCount++;
                                    $errors[] = "Student ID {$record->student_id}: {$e->getMessage()}";
                                    DB::rollBack();
                                }
                            }

                            // Log the bulk action
                            Log::warning('Bulk Quick Enroll used', [
                                'total_records' => count($records),
                                'success_count' => $successCount,
                                'failed_count' => $failedCount,
                                'admin_id' => Auth::id(),
                                'remarks' => $data['remarks'],
                                'errors' => $errors,
                            ]);

                            // Send notification
                            if ($successCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('⚡ Bulk Quick Enrollment Complete')
                                    ->body("Successfully enrolled {$successCount} student(s).".(
                                        $failedCount > 0 ? " {$failedCount} failed." : ''
                                    ))
                                    ->send();
                            }

                            if ($failedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('Some Enrollments Failed')
                                    ->body(implode("\n", array_slice($errors, 0, 5)).(count($errors) > 5 ? "\n... and ".(count($errors) - 5).' more' : ''))
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
