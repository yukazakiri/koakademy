<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\Actions\ChangeCourseAction;
use App\Filament\Resources\Students\StudentResource;
use App\Models\Account;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Services\StudentIdUpdateService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Auth;
use Mansoor\FilamentVersionable\Page\RevisionsAction;

final class ViewStudent extends ViewRecord
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            RevisionsAction::make(),
            ActionGroup::make([
                EditAction::make(),
                DeleteAction::make(),
                // evisionsAction::make(),
            ])
                ->label('Student Management')
                ->icon('heroicon-o-user')
                ->color('primary')
                ->button(),
            ActionGroup::make([
                Action::make('linkStudentAccount')
                    ->label('Link/Update Student Account')
                    ->icon('heroicon-o-user-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Link Student Account')
                    ->modalDescription('This will find and update the account associated with this student\'s email, setting the role to "student" and linking it to this student record.')
                    ->action(function ($record): void {
                        if (! $record->email) {
                            Notification::make()
                                ->warning()
                                ->title('No Email Found')
                                ->body('This student does not have an email address to link to an account.')
                                ->send();

                            return;
                        }

                        $account = Account::query()->where('email', $record->email)->first();

                        if (! $account) {
                            Notification::make()
                                ->warning()
                                ->title('No Account Found')
                                ->body('No account was found with the email: '.$record->email)
                                ->send();

                            return;
                        }

                        try {
                            $account->update([
                                'role' => 'student',
                                'person_id' => $record->id,
                                'person_type' => Student::class,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Account Linked')
                                ->body('Successfully linked account to student. Email: '.$record->email)
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Error Linking Account')
                                ->body('An error occurred: '.$exception->getMessage())
                                ->send();
                        }
                    }),

                // Update Student ID Action
                Action::make('updateStudentId')
                    ->label('Update Student ID')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Update Student ID')
                    ->modalDescription('This will update the student ID and all related records in the database. This action cannot be undone.')
                    ->schema([
                        Section::make('Current Information')
                            ->schema([
                                Placeholder::make('current_student_id')
                                    ->label('Current Student ID')
                                    ->content(fn ($record) => $record->student_id ?? 'Not set'),

                                Placeholder::make('primary_id')
                                    ->label('Primary ID (Database)')
                                    ->content(fn ($record) => $record->id),

                                Placeholder::make('student_name')
                                    ->label('Student Name')
                                    ->content(fn ($record) => $record->full_name),
                            ])
                            ->columns(3),

                        Section::make('Impact Summary')
                            ->schema([
                                Placeholder::make('affected_records')
                                    ->label('Records that will be updated')
                                    ->content(fn ($record): string => "This will only update the student_id column in the students table.\nNo related records will be modified.")
                                    ->columnSpanFull(),
                            ]),

                        Section::make('New ID')
                            ->schema([
                                TextInput::make('new_student_id')
                                    ->label('New Student ID')
                                    ->numeric()
                                    ->required()
                                    ->rules([
                                        'integer',
                                        'min:100000',
                                        'max:999999',
                                    ])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get, $component): void {
                                        if (! $state) {
                                            return;
                                        }

                                        // Convert to integer and validate
                                        if (! is_numeric($state)) {
                                            $component->state(null);
                                            Notification::make()
                                                ->title('Invalid Input')
                                                ->body('Student ID must be a number.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        $newStudentId = (int) $state;
                                        $model = $this->getRecord();
                                        $studentIdUpdateService = app(StudentIdUpdateService::class);

                                        // Check if same as current student_id
                                        if ($newStudentId === $model->student_id) {
                                            $component->state(null);
                                            Notification::make()
                                                ->title('Invalid ID')
                                                ->body('New student ID cannot be the same as current student ID.')
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        // Check if ID already exists
                                        if (! $studentIdUpdateService->isIdAvailable($newStudentId)) {
                                            $component->state(null);
                                            Notification::make()
                                                ->title('ID Already Exists')
                                                ->body(sprintf('Student ID %d already exists.', $newStudentId))
                                                ->danger()
                                                ->send();

                                            return;
                                        }

                                        // Check 6-digit format (100000-999999)
                                        if ($newStudentId < 100000 || $newStudentId > 999999) {
                                            $component->state(null);
                                            Notification::make()
                                                ->title('Invalid ID Format')
                                                ->body('Student ID must be exactly 6 digits (100000-999999).')
                                                ->warning()
                                                ->send();

                                            return;
                                        }
                                    })
                                    ->helperText(function (): string {
                                        $studentIdUpdateService = app(StudentIdUpdateService::class);
                                        $suggested = $studentIdUpdateService->generateSuggestedId();

                                        return 'Suggested ID: '.$suggested.' (next available 6-digit ID)';
                                    }),
                            ]),

                        Section::make('⚠️ Confirmation Required')
                            ->schema([
                                Checkbox::make('confirm_operation')
                                    ->label('I confirm this student ID change')
                                    ->helperText('This will update only the student_id column in the students table. Related records will NOT be affected.')
                                    ->required()
                                    ->accepted(),
                            ])
                            ->description('This operation will permanently change the student_id column only. No related records will be modified.')
                            ->icon('heroicon-o-exclamation-triangle'),
                    ])
                    ->action(function (array $data, Student $record): void {
                        $studentIdUpdateService = app(StudentIdUpdateService::class);
                        $oldStudentId = $record->student_id;
                        $newStudentId = (int) $data['new_student_id'];

                        // Send initial notification about the update process
                        Notification::make()
                            ->title('Updating Student ID...')
                            ->body(sprintf('Starting update from student ID %s to %d.', $oldStudentId ?? 'Not set', $newStudentId))
                            ->info()
                            ->duration(2000)
                            ->send();

                        // Perform the update with bypass safety checks since user confirmed
                        $result = $studentIdUpdateService->updateStudentId($record, $newStudentId, true);

                        if ($result['success']) {
                            $detailsMessage = "Student ID successfully changed from {$oldStudentId} to {$newStudentId}.\n\nOnly the student_id column was updated.";

                            Notification::make()
                                ->title('✅ Student ID Updated Successfully!')
                                ->body($detailsMessage)
                                ->success()
                                ->duration(5000)
                                ->send();

                            // Send database notification
                            Notification::make()
                                ->title('Student ID Updated')
                                ->body(sprintf('Student ID changed from %s to %d.', $oldStudentId ?? 'Not set', $newStudentId))
                                ->success()
                                ->sendToDatabase(Auth::user());

                            // Refresh the page to show updated data
                            $this->js('setTimeout(() => { window.location.reload(); }, 2000);');
                        } else {
                            Notification::make()
                                ->title('❌ Failed to Update Student ID')
                                ->body("Error: {$result['message']}\n\nNo changes were made to the database.")
                                ->danger()
                                ->duration(8000)
                                ->send();
                        }
                    }),

                // Undo Student ID Change Action
                Action::make('undoStudentIdChange')
                    ->label('Undo ID Change')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(function ($record) {
                        $studentIdUpdateService = app(StudentIdUpdateService::class);
                        $changes = $studentIdUpdateService->getStudentChangeHistory($record->id);

                        return $changes->where('is_undone', false)->isNotEmpty();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Undo Student ID Change')
                    ->modalDescription('This will revert the student ID back to its previous value and update all related records.')
                    ->schema([
                        Section::make('Available Changes to Undo')
                            ->schema([
                                Select::make('change_log_id')
                                    ->label('Select Change to Undo')
                                    ->options(function ($record) {
                                        $studentIdUpdateService = app(StudentIdUpdateService::class);
                                        $changes = $studentIdUpdateService->getStudentChangeHistory($record->id)
                                            ->where('is_undone', false);

                                        return $changes->mapWithKeys(function ($change): array {
                                            $date = $change->created_at->format('M j, Y g:i A');
                                            $label = sprintf('Changed from %s to %s on %s by %s', $change->old_student_id, $change->new_student_id, $date, $change->changed_by);

                                            return [$change->id => $label];
                                        })->toArray();
                                    })
                                    ->required()
                                    ->helperText('Select which ID change you want to undo'),
                            ]),

                        Section::make('⚠️ Confirmation')
                            ->schema([
                                Checkbox::make('confirm_undo')
                                    ->label('I confirm this undo operation')
                                    ->helperText('This will revert the student ID and update all related records')
                                    ->required()
                                    ->accepted(),
                            ]),
                    ])
                    ->action(function (array $data, $record): void {
                        $studentIdUpdateService = app(StudentIdUpdateService::class);
                        $changeLogId = (int) $data['change_log_id'];

                        // Send initial notification
                        Notification::make()
                            ->title('Processing Undo...')
                            ->body('Reverting student ID change. Please wait...')
                            ->info()
                            ->duration(2000)
                            ->send();

                        $result = $studentIdUpdateService->undoStudentIdChange($changeLogId);

                        if ($result['success']) {
                            // Create detailed success message
                            $message = sprintf('Student ID successfully reverted from %s back to %s.', $result['old_id'], $result['new_id']);

                            Notification::make()
                                ->title('✅ ID Change Undone Successfully!')
                                ->body($message)
                                ->success()
                                ->duration(5000)
                                ->send();

                            // Send database notification
                            Notification::make()
                                ->title('Student ID Change Undone')
                                ->body($message)
                                ->success()
                                ->sendToDatabase(Auth::user());

                            // Refresh the page to show updated data
                            $this->js('setTimeout(() => { window.location.reload(); }, 2000);');
                        } else {
                            Notification::make()
                                ->title('❌ Failed to Undo ID Change')
                                ->body('Error: '.$result['message'])
                                ->danger()
                                ->duration(8000)
                                ->send();

                            // Send database notification for failure
                            Notification::make()
                                ->title('Student ID Undo Failed')
                                ->body('Failed to undo student ID change: '.$result['message'])
                                ->danger()
                                ->sendToDatabase(Auth::user());
                        }
                    }),
            ])
                ->label('Account & System')
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->button(),

            // Academic Actions Group
            ActionGroup::make([
                ChangeCourseAction::make(),

                Action::make('retryClassEnrollment')
                    ->label('Retry Class Enrollment')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Class Enrollment?')
                    ->modalDescription('This will attempt to re-enroll the student in all available classes for their current subjects. Force enrollment is enabled by default to override maximum class size limits.')
                    ->schema([
                        Toggle::make('force_enrollment')
                            ->label('Force Enrollment')
                            ->helperText('Override maximum class size limits when enrolling')
                            ->default(true),
                        Select::make('enrollment_id')
                            ->label('Enrollment to Use')
                            ->options(fn ($record) => $record->subjectEnrolled()
                                ->select('enrollment_id')
                                ->distinct()
                                ->get()
                                ->pluck('enrollment_id', 'enrollment_id')
                                ->map(fn ($id): string => 'Enrollment #'.$id)
                                ->toArray())
                            ->helperText('Select which enrollment to use for class assignments. Leave empty to use all subjects.')
                            ->searchable()
                            ->placeholder('All Subjects'),
                    ])
                    ->action(function (array $data, $record): void {
                        // Temporarily override the force_enroll_when_full config if needed
                        $originalConfigValue = config('enrollment.force_enroll_when_full');
                        if ($data['force_enrollment']) {
                            config(['enrollment.force_enroll_when_full' => true]);
                        }

                        try {
                            // Attempt to auto-enroll using the specified enrollment ID or null for all subjects
                            $enrollmentId = $data['enrollment_id'] ?? null;
                            $record->autoEnrollInClasses($enrollmentId);

                            Notification::make()
                                ->success()
                                ->title('Enrollment Retry Complete')
                                ->body('The system has attempted to enroll the student in all classes. Check the notification for results.')
                                ->send();
                        } catch (Exception $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Enrollment Retry Failed')
                                ->body('An error occurred: '.$exception->getMessage())
                                ->send();
                        } finally {
                            // Restore original config value
                            if ($data['force_enrollment']) {
                                config(['enrollment.force_enroll_when_full' => $originalConfigValue]);
                            }
                        }
                    }),
            ])
                ->label('Academic Actions')
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->button(),

            // Financial Actions Group
            ActionGroup::make([
                Action::make('manageTuition')
                    ->label('Manage Tuition')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->modalHeading('Manage Student Tuition')
                    ->modalDescription('Update the tuition information for this student in the current semester')
                    ->modalSubmitActionLabel('Save Tuition Information')
                    ->fillForm(function ($record): array {
                        $tuition = $record->getCurrentTuitionModel();
                        $course = $record->Course;

                        if (! $tuition) {
                            return [
                                'total_lectures' => 0,
                                'total_laboratory' => 0,
                                'total_miscelaneous_fees' => $course ? $course->getMiscellaneousFee() : 3500,
                                'downpayment' => 0,
                                'discount' => 0,
                            ];
                        }

                        return [
                            'total_lectures' => $tuition->total_lectures,
                            'total_laboratory' => $tuition->total_laboratory,
                            'total_miscelaneous_fees' => $tuition->total_miscelaneous_fees,
                            'downpayment' => $tuition->downpayment,
                            'discount' => $tuition->discount,
                        ];
                    })
                    ->schema([
                        Section::make('Tuition Fees')
                            ->schema([
                                TextInput::make('total_lectures')
                                    ->label('Lecture Fees')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                                TextInput::make('total_laboratory')
                                    ->label('Laboratory Fees')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                                TextInput::make('total_miscelaneous_fees')
                                    ->label('Miscellaneous Fees')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(3500)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                            ])->columns(3),

                        Section::make('Payment Information')
                            ->schema([
                                TextInput::make('downpayment')
                                    ->label('Downpayment')
                                    ->numeric()
                                    ->prefix('₱')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                                TextInput::make('discount')
                                    ->label('Discount (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, $set, $get): void {
                                        $this->calculateTotals($set, $get);
                                    }),
                            ])->columns(2),

                        Section::make('Calculated Totals')
                            ->schema([
                                TextInput::make('total_tuition')
                                    ->label('Total Tuition (Lectures + Laboratory)')
                                    ->prefix('₱')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('overall_tuition')
                                    ->label('Overall Tuition (Including Misc Fees)')
                                    ->prefix('₱')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('total_balance')
                                    ->label('Total Balance')
                                    ->prefix('₱')
                                    ->disabled()
                                    ->dehydrated(false),
                            ])->columns(3),
                    ])
                    ->action(function (array $data, $record): void {
                        $tuition = $record->getOrCreateCurrentTuition();
                        $settings = GeneralSetting::query()->first();

                        // Calculate totals
                        $totalTuition = (float) $data['total_lectures'] + (float) $data['total_laboratory'];
                        $overallTuition = $totalTuition + (float) $data['total_miscelaneous_fees'];

                        // Apply discount
                        $discountAmount = $overallTuition * ((float) $data['discount'] / 100);
                        $overallTuitionAfterDiscount = $overallTuition - $discountAmount;

                        $totalBalance = $overallTuitionAfterDiscount - (float) $data['downpayment'];

                        $tuition->update([
                            'total_lectures' => (float) $data['total_lectures'],
                            'total_laboratory' => (float) $data['total_laboratory'],
                            'total_miscelaneous_fees' => (float) $data['total_miscelaneous_fees'],
                            'total_tuition' => $totalTuition,
                            'overall_tuition' => $overallTuitionAfterDiscount,
                            'downpayment' => (float) $data['downpayment'],
                            'discount' => (float) $data['discount'],
                            'total_balance' => $totalBalance,
                            'status' => $totalBalance <= 0 ? 'paid' : 'pending',
                        ]);

                        Notification::make()
                            ->title('Tuition Updated')
                            ->body('The student tuition information has been updated for the '.$settings->getSemester().' of '.$settings->getSchoolYearString())
                            ->success()
                            ->send();
                    }),
            ])
                ->label('Financial Management')
                ->icon('heroicon-o-currency-dollar')
                ->color('warning')
                ->button(),

            // Administrative Actions Group
            ActionGroup::make([
                Action::make('managePreviousSemesterClearance')
                    ->label('Quick Clear Previous Semester')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (): bool => (bool) GeneralSetting::query()->first()->enable_clearance_check)
                    ->fillForm(function ($record): array {
                        $previous = $record->getPreviousAcademicPeriod();
                        $clearance = $record->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        return [
                            'is_cleared' => $clearance?->is_cleared ?? false,
                            'remarks' => $clearance?->remarks ?? null,
                        ];
                    })
                    ->schema([
                        Placeholder::make('info')
                            ->label('Academic Period')
                            ->content(function ($record): string {
                                $previous = $record->getPreviousAcademicPeriod();

                                return "Managing clearance for {$previous['academic_year']} Semester {$previous['semester']}";
                            }),

                        Toggle::make('is_cleared')
                            ->label('Is Cleared')
                            ->helperText('Set whether this student has cleared their requirements for the previous semester')
                            ->required(),

                        Textarea::make('remarks')
                            ->label('Remarks')
                            ->placeholder('Enter any notes about this clearance status')
                            ->helperText('Optional notes about the clearance status')
                            ->columnSpan(2),
                    ])
                    ->action(function (array $data, $record): void {
                        $user = Auth::user();
                        $clearedBy = $user ? $user->name : 'System';
                        $previous = $record->getPreviousAcademicPeriod();

                        $clearance = $record->clearances()
                            ->where('academic_year', $previous['academic_year'])
                            ->where('semester', $previous['semester'])
                            ->first();

                        if (! $clearance) {
                            $clearance = \App\Models\StudentClearance::query()->create([
                                'student_id' => $record->id,
                                'academic_year' => $previous['academic_year'],
                                'semester' => $previous['semester'],
                                'is_cleared' => false,
                            ]);
                        }

                        $isCleared = $data['is_cleared'];
                        $clearance->update([
                            'is_cleared' => $isCleared,
                            'cleared_by' => $isCleared ? $clearedBy : null,
                            'cleared_at' => $isCleared ? now() : null,
                            'remarks' => $data['remarks'] ?? $clearance->remarks,
                        ]);

                        Notification::make()
                            ->title('Clearance Updated')
                            ->body('Student has been marked as '.($isCleared ? 'Cleared' : 'Not Cleared')." for {$previous['academic_year']} Semester {$previous['semester']}")
                            ->success()
                            ->send();
                    }),

                Action::make('manageClearance')
                    ->label('Manage Current Semester Clearance')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->visible(fn (): bool => (bool) GeneralSetting::query()->first()->enable_clearance_check)
                    ->schema([
                        Toggle::make('is_cleared')
                            ->label('Is Cleared')
                            ->helperText('Set whether this student has cleared their requirements for the current semester')
                            ->required(),

                        DateTimePicker::make('cleared_at')
                            ->label('Cleared At')
                            ->visible(fn (Get $get): bool => (bool) $get('is_cleared'))
                            ->default(now())
                            ->displayFormat('F j, Y g:i A')
                            ->seconds(false),

                        Textarea::make('remarks')
                            ->label('Remarks')
                            ->placeholder('Enter any notes about this clearance status')
                            ->helperText('Optional notes about the clearance status')
                            ->columnSpan(2),
                    ])
                    ->action(function (array $data, $record): void {
                        $user = Auth::user();
                        $clearedBy = $user ? $user->name : 'System';
                        $settings = GeneralSetting::query()->first();

                        if ($data['is_cleared']) {
                            $success = $record->markClearanceAsCleared(
                                $clearedBy,
                                $data['remarks'] ?? null
                            );

                            if ($success) {
                                Notification::make()
                                    ->title('Clearance Approved')
                                    ->body('The student has been cleared for the '.$settings->getSemester().' of '.$settings->getSchoolYearString())
                                    ->success()
                                    ->send();
                            }
                        } else {
                            $success = $record->markClearanceAsNotCleared($data['remarks'] ?? null);

                            if ($success) {
                                Notification::make()
                                    ->title('Clearance Status Updated')
                                    ->body('The student is marked as not cleared for the '.$settings->getSemester().' of '.$settings->getSchoolYearString())
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),

                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
                ->label('Administrative')
                ->icon('heroicon-o-shield-check')
                ->color('danger')
                ->button(),
        ];
    }
}
