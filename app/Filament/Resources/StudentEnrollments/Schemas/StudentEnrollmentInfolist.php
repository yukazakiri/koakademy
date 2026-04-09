<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Schemas;

use App\Enums\AcademicYear;
use App\Models\StudentTransaction;
use App\Models\Transaction;
use App\Services\EnrollmentPipelineService;
use App\Services\GeneralSettingsService;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final class StudentEnrollmentInfolist
{
    public $record;

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(4)
            ->components([

                Fieldset::make("Enrollee's Status")
                    ->columnSpanFull()
                    ->columns(4)
                    ->schema([
                        Fieldset::make('Student Information')
                            ->columns(3)
                            ->columnSpan(3)
                            ->schema([
                                TextEntry::make('student_id')
                                    ->badge()
                                    ->copyable()
                                    ->icon('phosphor-clipboard')
                                    ->label('Student ID'),

                                TextEntry::make('student.full_name')->label(
                                    'Student Full name'
                                ),
                                TextEntry::make('student.email')->label(
                                    'Student Email'
                                ),
                                TextEntry::make('student.academic_year')
                                    ->label('Year Level')
                                    ->formatStateUsing(
                                        fn ($state) => match ($state) {
                                            1 => AcademicYear::first,
                                            2 => AcademicYear::second,
                                            3 => AcademicYear::third,
                                            4 => AcademicYear::fourth,
                                            default => $state,
                                        }
                                    ),
                                TextEntry::make('student.course.code')
                                    ->badge()
                                    ->label('Course'),
                                Section::make('Subjects Enrolled')
                                    ->collapsed()
                                    ->columnSpanFull()
                                    ->schema([
                                        RepeatableEntry::make('subjectsEnrolled')
                                            ->columns(4)
                                            ->schema([
                                                TextEntry::make('subject.code')->label(
                                                    'Subject Code'
                                                ),
                                                TextEntry::make('subject.title')
                                                    ->label('Subject Title')
                                                    ->words(3),
                                                TextEntry::make('subject.units')->label(
                                                    'Units'
                                                ),
                                                TextEntry::make('school_year')->label(
                                                    'School Year'
                                                ),
                                                TextEntry::make('semester')->label(
                                                    'Semester'
                                                ),
                                            ])
                                            ->label('Subjects Enrolled'),
                                    ]),

                                Section::make('Class Enrollment Status')
                                    ->collapsed()
                                    ->columnSpanFull()
                                    ->schema([
                                        RepeatableEntry::make('enrolled_classes_for_infolist')
                                            ->label('Currently Enrolled Classes')
                                            ->visible(fn ($record): bool => $record->enrolled_classes_for_infolist->isNotEmpty())
                                            ->table([
                                                TableColumn::make('Subject Code')->width('120px'),
                                                TableColumn::make('Section')->width('80px'),
                                                TableColumn::make('Faculty')->width('150px'),
                                                TableColumn::make('Prelim')->width('70px'),
                                                TableColumn::make('Midterm')->width('70px'),
                                                TableColumn::make('Finals')->width('70px'),
                                                TableColumn::make('Average')->width('80px'),
                                                TableColumn::make('Status')->width('100px'),
                                                TableColumn::make('View Class')->width('100px'),
                                            ])
                                            ->schema([
                                                TextEntry::make('subject_code')->label('Subject Code'),
                                                TextEntry::make('section')->label('Section'),
                                                TextEntry::make('faculty')->label('Faculty'),
                                                TextEntry::make('grades.prelim')
                                                    ->label('Prelim')
                                                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2) : '—'),
                                                TextEntry::make('grades.midterm')
                                                    ->label('Midterm')
                                                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2) : '—'),
                                                TextEntry::make('grades.finals')
                                                    ->label('Finals')
                                                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2) : '—'),
                                                TextEntry::make('grades.average')
                                                    ->label('Average')
                                                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 2) : '—')
                                                    ->weight(FontWeight::Bold),
                                                TextEntry::make('status')
                                                    ->label('Status')
                                                    ->badge()
                                                    ->formatStateUsing(fn ($state): string => $state ? 'Active' : 'Inactive')
                                                    ->color(fn ($state): string => $state ? 'success' : 'gray'),
                                                Actions::make([
                                                    Action::make('view_class')
                                                        ->label('View Class')
                                                        ->icon('heroicon-m-eye')
                                                        ->color('primary')
                                                        ->action(function ($state, \Livewire\Component $livewire): void {
                                                            $classId = is_object($state) ? ($state->class_id ?? null) : data_get($state, 'class_id');

                                                            if (! $classId) {
                                                                return;
                                                            }

                                                            $livewire->redirect(
                                                                \App\Filament\Resources\Classes\ClassesResource::getUrl('view', ['record' => $classId]),
                                                                navigate: true,
                                                            );
                                                        }),
                                                ])->columnSpan(1),
                                            ])
                                            ->columns(10)
                                            ->columnSpanFull(),

                                        RepeatableEntry::make('missing_classes_for_infolist')
                                            ->label('Missing Classes (Not Yet Enrolled)')
                                            ->visible(fn ($record): bool => $record->missing_classes_for_infolist->isNotEmpty())
                                            ->table([
                                                TableColumn::make('Subject Code')->width('120px'),
                                                TableColumn::make('Subject Title')->width('220px'),
                                                TableColumn::make('Section')->width('80px'),
                                                TableColumn::make('Faculty')->width('150px'),
                                                TableColumn::make('Status')->width('140px'),
                                                TableColumn::make('Available Slots')->width('120px'),
                                                TableColumn::make('Action')->width('160px'),
                                            ])
                                            ->schema([
                                                TextEntry::make('subject_code')->label('Subject Code'),
                                                TextEntry::make('subject_title')
                                                    ->label('Subject Title')
                                                    ->words(3),
                                                TextEntry::make('section')->label('Section'),
                                                TextEntry::make('faculty')->label('Faculty'),
                                                TextEntry::make('enrollment_status')
                                                    ->label('Status')
                                                    ->badge()
                                                    ->formatStateUsing(fn ($state, $record): string => data_get($record, 'class_id')
                                                        ? 'Not Enrolled'
                                                        : 'No Class Offering')
                                                    ->color(fn ($state, $record): string => data_get($record, 'class_id') ? 'warning' : 'gray'),
                                                TextEntry::make('available_slots')
                                                    ->label('Available Slots')
                                                    ->formatStateUsing(function ($state, $record): string {
                                                        if (! data_get($record, 'class_id')) {
                                                            return '—';
                                                        }

                                                        $maxSlots = (int) data_get($record, 'max_slots', 0);

                                                        if ($maxSlots <= 0) {
                                                            return 'Unlimited';
                                                        }

                                                        return sprintf('%d/%d', (int) $state, $maxSlots);
                                                    })
                                                    ->color(function ($record): string {
                                                        if (! data_get($record, 'class_id')) {
                                                            return 'gray';
                                                        }

                                                        return data_get($record, 'is_full') ? 'danger' : 'success';
                                                    }),
                                                Actions::make([
                                                    Action::make('view_class')
                                                        ->label('View Class')
                                                        ->icon('heroicon-m-eye')
                                                        ->color('primary')
                                                        ->visible(fn ($state): bool => (bool) data_get($state, 'class_id'))
                                                        ->action(function ($state, \Livewire\Component $livewire): void {
                                                            $classId = is_object($state) ? ($state->class_id ?? null) : data_get($state, 'class_id');

                                                            if (! $classId) {
                                                                return;
                                                            }

                                                            $livewire->redirect(
                                                                \App\Filament\Resources\Classes\ClassesResource::getUrl('view', ['record' => $classId]),
                                                                navigate: true,
                                                            );
                                                        }),
                                                    Action::make('enroll_in_class')
                                                        ->label('Enroll')
                                                        ->icon('heroicon-m-plus-circle')
                                                        ->color('success')
                                                        ->visible(function ($record): bool {
                                                            $classId = data_get($record, 'class_id');
                                                            $isFull = (bool) data_get($record, 'is_full');

                                                            return (bool) $classId && ! $isFull;
                                                        })
                                                        ->action(function ($state, $component): void {
                                                            // Convert state to array if it's an object
                                                            $record = is_object($state) ? (array) $state : $state;
                                                            $component->ownerRecord->refresh();
                                                            $enrollment = $component->ownerRecord;
                                                            $student = $enrollment->student;

                                                            try {
                                                                // Check if already enrolled
                                                                $existingEnrollment = \App\Models\ClassEnrollment::where('class_id', $record['class_id'])
                                                                    ->where('student_id', $student->id)
                                                                    ->first();

                                                                if ($existingEnrollment) {
                                                                    Notification::make()
                                                                        ->warning()
                                                                        ->title('Already Enrolled')
                                                                        ->body('Student is already enrolled in this class.')
                                                                        ->send();

                                                                    return;
                                                                }

                                                                // Create the enrollment
                                                                \App\Models\ClassEnrollment::create([
                                                                    'class_id' => $record['class_id'],
                                                                    'student_id' => $student->id,
                                                                    'status' => true,
                                                                ]);

                                                                Notification::make()
                                                                    ->success()
                                                                    ->title('Enrollment Successful')
                                                                    ->body("Student has been enrolled in {$record['subject_code']} - Section {$record['section']}.")
                                                                    ->send();

                                                                // Refresh the component
                                                                $component->dispatch('$refresh');
                                                            } catch (Exception $e) {
                                                                Notification::make()
                                                                    ->danger()
                                                                    ->title('Enrollment Failed')
                                                                    ->body('Error: '.$e->getMessage())
                                                                    ->send();
                                                            }
                                                        }),
                                                    Action::make('force_enroll_in_class')
                                                        ->label('Force Enroll')
                                                        ->icon('heroicon-m-exclamation-triangle')
                                                        ->color('warning')
                                                        ->visible(function ($record): bool {
                                                            $classId = data_get($record, 'class_id');
                                                            $isFull = (bool) data_get($record, 'is_full');

                                                            return (bool) $classId && $isFull;
                                                        })
                                                        ->requiresConfirmation()
                                                        ->modalHeading('Force Enrollment')
                                                        ->modalDescription('This class is full. Are you sure you want to force enrollment?')
                                                        ->action(function ($state, $component): void {
                                                            // Convert state to array if it's an object
                                                            $record = is_object($state) ? (array) $state : $state;
                                                            $component->ownerRecord->refresh();
                                                            $enrollment = $component->ownerRecord;
                                                            $student = $enrollment->student;

                                                            try {
                                                                // Check if already enrolled
                                                                $existingEnrollment = \App\Models\ClassEnrollment::where('class_id', $record['class_id'])
                                                                    ->where('student_id', $student->id)
                                                                    ->first();

                                                                if ($existingEnrollment) {
                                                                    Notification::make()
                                                                        ->warning()
                                                                        ->title('Already Enrolled')
                                                                        ->body('Student is already enrolled in this class.')
                                                                        ->send();

                                                                    return;
                                                                }

                                                                // Create the enrollment
                                                                \App\Models\ClassEnrollment::create([
                                                                    'class_id' => $record['class_id'],
                                                                    'student_id' => $student->id,
                                                                    'status' => true,
                                                                ]);

                                                                Notification::make()
                                                                    ->success()
                                                                    ->title('Force Enrollment Successful')
                                                                    ->body("Student has been force-enrolled in {$record['subject_code']} - Section {$record['section']}.")
                                                                    ->send();

                                                                // Refresh the component
                                                                $component->dispatch('$refresh');
                                                            } catch (Exception $e) {
                                                                Notification::make()
                                                                    ->danger()
                                                                    ->title('Force Enrollment Failed')
                                                                    ->body('Error: '.$e->getMessage())
                                                                    ->send();
                                                            }
                                                        }),
                                                ])->columnSpan(1),
                                            ])
                                            ->columns(7)
                                            ->columnSpanFull(),

                                        TextEntry::make('no_classes')
                                            ->label('')
                                            ->visible(fn ($record): bool => $record->enrolled_classes_for_infolist->isEmpty() && $record->missing_classes_for_infolist->isEmpty())
                                            ->formatStateUsing(fn ($state, $record): string => 'No class enrollment information available for this enrollment period.')
                                            ->color('gray')
                                            ->icon('heroicon-o-book-open')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make("Enrollee's Schedule")
                                    ->collapsed()
                                    ->columnSpanFull()
                                    ->schema([
                                        ViewEntry::make('classSchedule')->view(
                                            'infolists.components.enrollee-sched'
                                        ),
                                    ]),
                            ]),
                        Fieldset::make("Enrollee's Status")
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->columnSpanFull()
                                    ->icon(function ($record): string {
                                        $pipeline = app(EnrollmentPipelineService::class);

                                        return match ($record->status) {
                                            $pipeline->getPendingStatus() => 'phosphor-x-fill',
                                            $pipeline->getDepartmentVerifiedStatus() => 'phosphor-check-square-offset',
                                            $pipeline->getCashierVerifiedStatus() => 'phosphor-check-circle',
                                            default => 'phosphor-question-mark-light',
                                        };
                                    })
                                    ->color(function ($record): string {
                                        $pipeline = app(EnrollmentPipelineService::class);

                                        return match ($record->status) {
                                            $pipeline->getPendingStatus() => 'warning',
                                            $pipeline->getDepartmentVerifiedStatus() => 'success',
                                            $pipeline->getCashierVerifiedStatus() => 'primary',
                                            default => 'gray',
                                        };
                                    }),
                                ViewEntry::make('signature.depthead_signature')
                                    ->label('Department head Signature')
                                    ->visible(
                                        fn ($record): bool => isset(
                                            $record->signature->depthead_signature
                                        )
                                    )
                                    ->columnSpanFull()
                                    ->view('infolists.components.signature-view'),
                                ViewEntry::make('signature.registrar_signature')
                                    ->label('Registrar Signature')
                                    ->visible(
                                        fn ($record): bool => isset(
                                            $record->signature->registrar_signature
                                        )
                                    )
                                    ->view('infolists.components.signature-view'),
                                ViewEntry::make('signature.cashier_signature')
                                    ->label('Cashier Signature')
                                    ->visible(
                                        fn ($record): bool => isset(
                                            $record->signature->cashier_signature
                                        )
                                    )
                                    ->view('infolists.components.signature-view'),
                                TextEntry::make('studentTuition.discount')
                                    ->label('Discount')
                                    ->columnSpanFull()
                                    ->prefix('%'),
                                TextEntry::make('studentTuition.total_lectures')
                                    ->label('Total Lecture Fee')
                                    ->columnSpanFull()
                                    ->prefix('₱'),
                                TextEntry::make('studentTuition.total_laboratory')
                                    ->label('Total Laboratory Fee')
                                    ->columnSpanFull()
                                    ->prefix('₱'),
                                TextEntry::make(
                                    'studentTuition.total_miscelaneous_fees'
                                )
                                    ->label('Total Miscellaneous Fee')
                                    ->columnSpanFull()
                                    ->prefix('₱'),

                                // Additional Fees Section
                                RepeatableEntry::make('additionalFees')
                                    ->label('Additional Fees')
                                    ->schema([
                                        TextEntry::make('fee_name')
                                            ->label('Fee')
                                            ->weight(FontWeight::Light),
                                        TextEntry::make('amount')
                                            ->label('Amount')
                                            ->formatStateUsing(fn ($state): string => '₱ '.number_format((float) $state, 2))
                                            ->weight(FontWeight::Bold)
                                            ->color('success'),

                                    ])
                                    ->columns(2)
                                    ->columnSpanFull()
                                    ->visible(fn ($record) => $record->additionalFees->isNotEmpty()),

                                // Additional Fees Total
                                TextEntry::make('additional_fees_total')
                                    ->label('Additional Fees Total')
                                    ->formatStateUsing(function ($record): string {
                                        $total = $record->additionalFees->sum('amount');

                                        return '₱ '.number_format((float) $total, 2);
                                    })
                                    ->weight(FontWeight::Bold)
                                    ->color('warning')
                                    ->columnSpanFull()
                                    ->visible(fn ($record) => $record->additionalFees->isNotEmpty()),

                                TextEntry::make('studentTuition.overall_tuition')
                                    ->label('Overall Tuition Fee')
                                    ->columnSpanFull()
                                    ->prefix('₱'),
                                TextEntry::make('studentTuition.downpayment')
                                    ->label('Down Payment')
                                    ->columnSpanFull()
                                    ->prefix('₱')
                                    ->tooltip('Reapply Transaction')
                                    ->suffixAction(
                                        Action::make('Reapply Downpayment')
                                            ->icon('heroicon-m-arrow-uturn-left')
                                            ->requiresConfirmation()
                                            ->action(function ($record) {
                                                DB::beginTransaction();
                                                try {
                                                    $tuition = $record->studentTuition;
                                                    $previous_balance =
                                                        $tuition->total_balance;
                                                    $tuition->total_balance -=
                                                        $tuition->downpayment;
                                                    $tuition->save();
                                                    Notification::make(
                                                        'Successfully Reapplied Transaction'
                                                    )
                                                        ->success()
                                                        ->body(
                                                            'Balance: ₱'.
                                                                $tuition->total_balance.
                                                                ' has been updated'.
                                                                'Previous Balance: ₱'.
                                                                $previous_balance
                                                        )
                                                        ->send();
                                                    DB::commit();
                                                } catch (Exception $exception) {
                                                    DB::rollBack();
                                                    Notification::make(
                                                        'Failed to Reapply Transaction'
                                                    )
                                                        ->danger()
                                                        ->body($exception->getMessage())
                                                        ->send();
                                                }

                                                return $record;
                                            })
                                    ),
                                TextEntry::make('studentTuition.total_balance')
                                    ->label('Balance')
                                    ->columnSpanFull()
                                    ->prefix('₱'),
                            ]),

                        Section::make('Transaction Details')
                            ->collapsed()
                            ->columnSpanFull()
                            ->headerActions([
                                Action::make('createTransaction')
                                    ->label('Create New Transaction')
                                    ->icon('heroicon-m-plus')
                                    ->color('primary')
                                    ->modalHeading('Create New Transaction')
                                    ->modalDescription(function (): string {
                                        $record = $this->record;
                                        $schoolYear = $record->school_year ?? 'Unknown';
                                        $semester = $record->semester ?? 'Unknown';

                                        return sprintf('Create a new transaction for this enrollment (%s, Semester %s)', $schoolYear, $semester);
                                    })
                                    ->schema([
                                        TextInput::make('description')
                                            ->label('Description')
                                            ->required()
                                            ->default(function (): string {
                                                $enrollment = $this->record;

                                                return sprintf('Payment for enrollment (%s, Semester %s)', $enrollment->school_year, $enrollment->semester);
                                            })
                                            ->maxLength(255),
                                        TextInput::make('invoicenumber')
                                            ->label('Invoice/O.R. Number')
                                            ->required()
                                            ->maxLength(255)
                                            ->placeholder('Enter invoice number'),
                                        KeyValue::make('settlements')
                                            ->label('Settlements')
                                            ->columnSpanFull()
                                            ->helperText('Enter the settlement amounts for different fees')
                                            ->default([
                                                'registration_fee' => 0,
                                                'tuition_fee' => 0,
                                                'miscelanous_fee' => 0,
                                                'diploma_or_certificate' => 0,
                                                'transcript_of_records' => 0,
                                                'certification' => 0,
                                                'special_exam' => 0,
                                                'others' => 0,
                                            ])
                                            ->reorderable()
                                            ->editableKeys(false)
                                            ->keyLabel('Particulars')
                                            ->deletable(false)
                                            ->addable(false)
                                            ->valueLabel('Amounts')
                                            ->required(),
                                        Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'pending' => 'Pending',
                                                'completed' => 'Completed',
                                                'cancelled' => 'Cancelled',
                                            ])
                                            ->default('completed')
                                            ->required(),
                                    ])
                                    ->action(function (array $data): void {
                                        try {
                                            DB::beginTransaction();

                                            $enrollment = $this->record;
                                            $student = $enrollment->student;

                                            // Create the transaction
                                            $transaction = Transaction::query()->create([
                                                'description' => $data['description'],
                                                'status' => $data['status'],
                                                'transaction_date' => now(),
                                                'invoicenumber' => $data['invoicenumber'],
                                                'settlements' => $data['settlements'],
                                            ]);

                                            // Create the student transaction relationship
                                            $totalAmount = array_sum(array_values($data['settlements']));
                                            StudentTransaction::query()->create([
                                                'student_id' => $student->id,
                                                'transaction_id' => $transaction->id,
                                                'amount' => $totalAmount,
                                                'status' => $data['status'],
                                            ]);

                                            DB::commit();

                                            Notification::make()
                                                ->title('Transaction Created Successfully')
                                                ->body(sprintf('Transaction #%s created with total amount of ₱', $transaction->transaction_number).number_format($totalAmount, 2))
                                                ->success()
                                                ->send();

                                        } catch (Exception $exception) {
                                            DB::rollBack();
                                            Notification::make()
                                                ->title('Transaction Creation Failed')
                                                ->body($exception->getMessage())
                                                ->danger()
                                                ->send();
                                        }
                                    }),
                            ])
                            ->schema([
                                RepeatableEntry::make('enrollmentTransactions')
                                    ->label('Enrollment Transactions')
                                    ->visible(fn ($record): bool => $record->enrollmentTransactions->count() > 0)
                                    ->schema([
                                        TextEntry::make('transaction_number')
                                            ->label('Transaction #')
                                            ->badge()
                                            ->color('primary')
                                            ->weight(FontWeight::Bold)
                                            ->copyable(),
                                        TextEntry::make('invoicenumber')
                                            ->label('Invoice/O.R. Number')
                                            ->badge()
                                            ->color('success')
                                            ->copyable()
                                            ->suffixAction(
                                                Action::make('editInvoice')
                                                    ->icon('heroicon-m-pencil-square')
                                                    ->tooltip('Edit Invoice Number')
                                                    ->color('warning')
                                                    ->modalHeading('Edit Invoice Number')
                                                    ->modalDescription('Update the invoice/O.R. number for this transaction')
                                                    ->schema([
                                                        TextInput::make('invoicenumber')
                                                            ->label('Invoice/O.R. Number')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->placeholder('Enter invoice number'),
                                                    ])
                                                    ->fillForm(fn ($record): array => [
                                                        'invoicenumber' => $record->invoicenumber,
                                                    ])
                                                    ->action(function (array $data, $record): void {
                                                        try {
                                                            $record->update([
                                                                'invoicenumber' => $data['invoicenumber'],
                                                            ]);

                                                            Notification::make()
                                                                ->title('Invoice Number Updated')
                                                                ->body('Invoice number updated to: '.$data['invoicenumber'])
                                                                ->success()
                                                                ->send();

                                                        } catch (Exception $exception) {
                                                            Notification::make()
                                                                ->title('Update Failed')
                                                                ->body($exception->getMessage())
                                                                ->danger()
                                                                ->send();
                                                        }
                                                    })
                                            ),
                                        TextEntry::make('description')
                                            ->label('Description')
                                            ->limit(50),
                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (string $state): string => match ($state) {
                                                'completed' => 'success',
                                                'pending' => 'warning',
                                                'cancelled' => 'danger',
                                                default => 'gray',
                                            }),
                                        TextEntry::make('total_amount')
                                            ->label('Total Amount')
                                            ->prefix('₱')
                                            ->weight(FontWeight::Bold)
                                            ->color('success')
                                            ->suffixAction(
                                                Action::make('editDownpayment')
                                                    ->icon('heroicon-m-banknotes')
                                                    ->tooltip('Update Downpayment')
                                                    ->color('success')
                                                    ->modalHeading('Update Downpayment')
                                                    ->modalDescription("Update the downpayment amount and apply it to the student's tuition balance")
                                                    ->schema(function ($record): array {
                                                        $enrollment = $this->record; // Get the current enrollment record
                                                        $currentDownpayment = $enrollment->studentTuition?->downpayment ?? 0;

                                                        return [
                                                            TextInput::make('downpayment')
                                                                ->label('New Downpayment Amount')
                                                                ->required()
                                                                ->numeric()
                                                                ->prefix('₱')
                                                                ->step(0.01)
                                                                ->minValue(0)
                                                                ->default($currentDownpayment)
                                                                ->helperText('Current downpayment: ₱'.number_format($currentDownpayment, 2)),
                                                            TextInput::make('transaction_description')
                                                                ->label('Transaction Description')
                                                                ->default('Downpayment update for enrollment')
                                                                ->maxLength(255),
                                                        ];
                                                    })
                                                    ->action(function (array $data, $record): void {
                                                        try {
                                                            DB::beginTransaction();

                                                            $enrollment = $this->record;
                                                            $tuition = $enrollment->studentTuition;

                                                            if (! $tuition) {
                                                                throw new Exception('No tuition record found for this enrollment');
                                                            }

                                                            $oldDownpayment = $tuition->downpayment ?? 0;
                                                            $newDownpayment = (float) $data['downpayment'];
                                                            $difference = $newDownpayment - $oldDownpayment;

                                                            // Update tuition record
                                                            $tuition->update([
                                                                'downpayment' => $newDownpayment,
                                                                'total_balance' => $tuition->total_balance - $difference,
                                                            ]);

                                                            // Update the transaction settlements
                                                            $settlements = $record->settlements;
                                                            if (is_string($settlements)) {
                                                                $settlements = json_decode($settlements, true);
                                                            }

                                                            if (! is_array($settlements)) {
                                                                $settlements = [];
                                                            }

                                                            $settlements['tuition_fee'] = $newDownpayment;

                                                            $record->update([
                                                                'settlements' => $settlements,
                                                                'description' => $data['transaction_description'] ?? $record->description,
                                                            ]);

                                                            DB::commit();

                                                            Notification::make()
                                                                ->title('Downpayment Updated Successfully')
                                                                ->body('Downpayment updated from ₱'.number_format($oldDownpayment, 2).' to ₱'.number_format($newDownpayment, 2).'. Balance adjusted by ₱'.number_format($difference, 2))
                                                                ->success()
                                                                ->send();

                                                        } catch (Exception $exception) {
                                                            DB::rollBack();
                                                            Notification::make()
                                                                ->title('Update Failed')
                                                                ->body($exception->getMessage())
                                                                ->danger()
                                                                ->send();
                                                        }
                                                    })
                                            ),
                                        TextEntry::make('transaction_date')
                                            ->label('Transaction Date')
                                            ->dateTime(),
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime()
                                            ->since(),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),

                                TextEntry::make('no_transactions')
                                    ->label('')
                                    ->visible(fn ($record): bool => $record->enrollmentTransactions->count() === 0)
                                    ->formatStateUsing(function ($state, $record): string {
                                        $schoolYear = $record->school_year ?? 'Unknown';
                                        $semester = $record->semester ?? 'Unknown';

                                        // Debug information
                                        $generalSettingsService = new GeneralSettingsService;
                                        $schoolStartingDate = $generalSettingsService->getGlobalSchoolStartingDate();
                                        $schoolEndingDate = $generalSettingsService->getGlobalSchoolEndingDate();

                                        $debugInfo = '';
                                        if ($schoolStartingDate && $schoolEndingDate) {
                                            $debugInfo = sprintf(' | School Calendar: %s to %s', $schoolStartingDate->format('Y-m-d'), $schoolEndingDate->format('Y-m-d'));
                                        }

                                        return sprintf('No transactions found for this enrollment period (%s, Semester %s)%s.', $schoolYear, $semester, $debugInfo);
                                    })
                                    ->color('gray')
                                    ->icon('heroicon-o-banknotes')
                                    ->columnSpanFull(),
                            ]),

                        Fieldset::make('Resources')
                            ->columnSpanFull()
                            ->schema([
                                RepeatableEntry::make('resources')
                                    ->schema([
                                        TextEntry::make('type')
                                            ->badge()
                                            ->color(
                                                fn (string $state): string => match (
                                                    $state
                                                ) {
                                                    'assessment' => 'success',
                                                    'certificate' => 'warning',
                                                    default => 'gray',
                                                }
                                            )
                                            ->columnSpan(1),
                                        TextEntry::make('file_name')
                                            ->label('File Name')
                                            ->copyable()
                                            ->columnSpan(2),
                                        TextEntry::make('file_size')
                                            ->label('Size')
                                            ->formatStateUsing(
                                                fn ($state): string => $state
                                                    ? number_format((float) $state / 1024, 2).
                                                        ' KB'
                                                    : 'Unknown'
                                            )
                                            ->columnSpan(1),
                                        TextEntry::make('created_at')
                                            ->label('Created')
                                            ->dateTime()
                                            ->since()
                                            ->columnSpan(1),
                                        Actions::make([
                                            /* Action::make("view")
                                        ->label("View")
                                        ->icon("heroicon-m-eye")
                                        ->color("primary")
                                        ->url(
                                            fn($record) => route(
                                                "view-resource",
                                                ["resource" => $record->id]
                                            )
                                        )
                                        ->openUrlInNewTab(), */
                                            /* Action::make("download")
                                        ->label("Download")
                                        ->icon("heroicon-m-arrow-down-tray")
                                        ->color("info")
                                        ->url(
                                            fn($record) => route(
                                                "view-resource",
                                                ["resource" => $record->id]
                                            )
                                        )
                                        ->openUrlInNewTab(), */
                                            Action::make('delete')
                                                ->label('Delete')
                                                ->icon('heroicon-m-trash')
                                                ->color('danger')
                                                ->requiresConfirmation()
                                                ->modalHeading('Delete Resource')
                                                ->modalDescription(
                                                    'Are you sure you want to delete this resource? This action cannot be undone.'
                                                )
                                                ->action(function ($record): void {
                                                    try {
                                                        // Delete the physical file
                                                        if (
                                                            file_exists(
                                                                $record->file_path
                                                            )
                                                        ) {
                                                            unlink($record->file_path);
                                                        }

                                                        // Try to delete from storage disk as well
                                                        try {
                                                            Storage::disk(
                                                                $record->disk
                                                            )->delete(
                                                                $record->file_name
                                                            );
                                                        } catch (Exception $e) {
                                                            // Log but don't fail if storage deletion fails
                                                            Log::warning(
                                                                'Failed to delete file from storage disk',
                                                                [
                                                                    'file_name' => $record->file_name,
                                                                    'disk' => $record->disk,
                                                                    'error' => $e->getMessage(),
                                                                ]
                                                            );
                                                        }

                                                        // Delete the database record
                                                        $record->delete();

                                                        Notification::make()
                                                            ->title('Resource Deleted')
                                                            ->body(
                                                                'The resource has been successfully deleted.'
                                                            )
                                                            ->success()
                                                            ->send();
                                                    } catch (Exception $exception) {
                                                        Notification::make()
                                                            ->title('Deletion Failed')
                                                            ->body(
                                                                'Failed to delete the resource: '.
                                                                    $exception->getMessage()
                                                            )
                                                            ->danger()
                                                            ->send();
                                                    }
                                                }),
                                        ])->columnSpan(1),
                                    ])
                                    ->columnSpanFull()
                                    ->columns(6)
                                    ->contained(false)
                                    ->extraAttributes([
                                        'class' => 'overflow-x-auto',
                                        'style' => 'min-width: 100%; white-space: nowrap;',
                                    ]),
                            ]),

                    ]),
            ]);

    }
}
