<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Pages;

use App\Enums\PaymentMethod;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Models\StudentEnrollment;
use App\Services\EnrollmentPipelineService;
use App\Services\EnrollmentService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

final class ViewStudentEnrollment extends ViewRecord
{
    protected static string $resource = StudentEnrollmentResource::class;

    /**
     * Get class enrollment status for the student
     */
    public function getClassEnrollmentStatus(): array
    {
        $record = $this->getRecord();
        $student = $record->student;

        if (! $student) {
            return [
                'enrolled_classes' => collect(),
                'missing_classes' => collect(),
            ];
        }

        // Get all subject enrollments for this student enrollment
        $subjectEnrollments = $record->subjectsEnrolled ?? collect();

        $generalSettingsService = app(\App\Services\GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        // Get classes student is already enrolled in (active enrollments only)
        // Match the pattern from ClassEnrollmentsRelationManager
        $enrolledClasses = $student->classEnrollments()
            ->with('class')
            ->where('status', true)
            ->whereHas('class', function ($query) use ($currentSchoolYear, $currentSemester): void {
                $query->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester);
            })
            ->get()
            ->map(fn ($classEnrollment): array => [
                'id' => $classEnrollment->id,
                'class_id' => $classEnrollment->class_id,
                'subject_code' => $classEnrollment->class->subject_code,
                'subject_title' => $classEnrollment->class->subject_title,
                'section' => $classEnrollment->class->section,
                'faculty' => $classEnrollment->class->faculty->full_name ?? 'TBA',
                'schedule' => $classEnrollment->class->schedule ?? 'TBA',
                'room' => $classEnrollment->class->room ?? 'TBA',
                'enrolled_at' => $classEnrollment->created_at,
                'status' => $classEnrollment->status,
                'grades' => [
                    'prelim' => $classEnrollment->prelim_grade,
                    'midterm' => $classEnrollment->midterm_grade,
                    'finals' => $classEnrollment->finals_grade,
                    'average' => $classEnrollment->total_average,
                ],
            ]);

        // Find classes student should be enrolled in but isn't
        $enrolledSubjectCodes = $enrolledClasses->pluck('subject_code')->toArray();
        $missingClasses = [];

        foreach ($subjectEnrollments as $subjectEnrollment) {
            $subject = $subjectEnrollment->subject;
            $subjectCode = $subject->code;

            if (! in_array($subjectCode, $enrolledSubjectCodes)) {
                // Find available classes for this subject
                $availableClasses = \App\Models\Classes::query()
                    ->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester)
                    ->whereJsonContains('course_codes', $subject->course_id)
                    ->where(function ($query) use ($subject): void {
                        $query->whereJsonContains('subject_ids', $subject->id)
                            ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                            ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
                    })
                    ->get()
                    ->map(function ($class) use ($subjectCode): array {
                        $enrolledCount = \App\Models\ClassEnrollment::where('class_id', $class->id)->count();
                        $maxSlots = $class->maximum_slots ?: 0;
                        $availableSlots = $maxSlots > 0 ? $maxSlots - $enrolledCount : PHP_INT_MAX;

                        return [
                            'class_id' => $class->id,
                            'subject_code' => $subjectCode,
                            'subject_title' => $class->subject_title,
                            'section' => $class->section,
                            'faculty' => $class->faculty->full_name ?? 'TBA',
                            'schedule' => $class->schedule ?? 'TBA',
                            'room' => $class->room ?? 'TBA',
                            'available_slots' => $availableSlots,
                            'max_slots' => $maxSlots,
                            'is_full' => $maxSlots > 0 && $enrolledCount >= $maxSlots,
                        ];
                    });

                foreach ($availableClasses as $availableClass) {
                    $missingClasses[] = $availableClass;
                }
            }
        }

        return [
            'enrolled_classes' => $enrolledClasses,
            'missing_classes' => collect($missingClasses),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // Primary Actions
            // Quick Enroll Action - Skip all verification steps
            Action::make('quickEnrollNoReceipt')
                ->label('⚡ Quick Enroll (No Receipt)')
                ->icon('heroicon-o-bolt')
                ->color('danger')
                ->modalHeading('⚡ Quick Enroll Student Without Receipt')
                ->modalDescription(
                    'EMERGENCY USE ONLY: This will skip both Department Head and Cashier verification steps and directly enroll the student without creating a transaction record. This action is logged and audited. Super Admin access only.'
                )
                ->form([
                    Placeholder::make('emergency_warning')
                        ->label('')
                        ->content('🚨 EMERGENCY ENROLLMENT - This bypasses all normal verification workflows. Use only in exceptional circumstances.')
                        ->columnSpanFull(),
                    Textarea::make('remarks')
                        ->label('Justification & Verification Details')
                        ->placeholder('REQUIRED: Explain why normal verification is being bypassed and how payment was verified...')
                        ->helperText('Document: reason for emergency enrollment, payment verification method, authorization details')
                        ->required()
                        ->rows(5)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                    Toggle::make('confirm_emergency')
                        ->label('I confirm this is an emergency enrollment and I have authorization to bypass normal verification')
                        ->required()
                        ->accepted()
                        ->helperText('By confirming, you acknowledge full responsibility for this enrollment')
                        ->columnSpanFull(),
                    Toggle::make('confirm_payment')
                        ->label('I confirm that payment has been verified through alternative means')
                        ->required()
                        ->accepted()
                        ->columnSpanFull(),
                ])
                ->visible(function (StudentEnrollment $record): bool {
                    $pipeline = app(EnrollmentPipelineService::class);

                    return $pipeline->isPending($record->status) && Auth::user()->hasRole('super_admin');
                })
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-bolt')
                ->modalSubmitActionLabel('Yes, Quick Enroll Now')
                ->action(function (
                    StudentEnrollment $record,
                    array $data,
                    EnrollmentService $enrollmentService
                ): void {
                    try {
                        // First, mark as verified by head dept (without creating signature)
                        $record->status = app(EnrollmentPipelineService::class)->getDepartmentVerifiedStatus();
                        $record->save();

                        // Then use the no-receipt verification
                        $success = $enrollmentService->verifyByCashierWithoutReceipt(
                            $record,
                            [
                                'remarks' => '⚡ QUICK ENROLL (Emergency): '.$data['remarks'],
                            ]
                        );

                        if ($success) {
                            Notification::make()
                                ->success()
                                ->title('⚡ Quick Enrollment Successful')
                                ->body('Student has been enrolled via emergency quick enroll. All verification steps were bypassed. Record remains active for analytics.')
                                ->persistent()
                                ->send();

                            // Log this action with more details
                            Log::warning('Emergency Quick Enroll used', [
                                'enrollment_id' => $record->id,
                                'student_id' => $record->student_id,
                                'student_name' => $record->student?->full_name,
                                'admin_id' => Auth::id(),
                                'admin_name' => Auth::user()->name,
                                'remarks' => $data['remarks'],
                                'timestamp' => now(),
                            ]);

                            $this->refreshFormData(['status', 'remarks']);
                        } else {
                            $this->halt();
                        }
                    } catch (Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Quick Enrollment Failed')
                            ->body('Error: '.$e->getMessage())
                            ->send();
                        $this->halt();
                    }
                }),

            Action::make('verifyAsHeadDept')
                ->label('Verify as Dept Head')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(function (StudentEnrollment $record): bool {
                    $pipeline = app(EnrollmentPipelineService::class);

                    return $pipeline->isPending($record->status) && (Auth::user()->can(
                        'verify_by_head_dept_guest::enrollment'
                    ) ||
                        Auth::user()->hasRole('super_admin'));
                })
                ->requiresConfirmation()
                ->action(function (
                    StudentEnrollment $record,
                    array $data,
                    EnrollmentService $enrollmentService
                ): void {
                    $signature = $data['signature'] ?? null;
                    $success = $enrollmentService->verifyByHeadDept(
                        $record
                    );
                    if (! $success) {
                        $this->halt();
                    }
                    $this->refreshFormData([
                        'status',
                        'signature.depthead_signature',
                    ]);
                }),

            Action::make('verifyAsCashier')
                ->label('Enroll This Student')
                ->icon('heroicon-o-check')
                ->color('success')
                ->modalHeading('Pending Payment')
                ->modalDescription(
                    'This student has not yet paid the down payment. Please enter the amount of the down payment.'
                )
                ->form(function ($record): array {
                    // Get additional fees that require separate transactions
                    $separateTransactionFees = $record->additionalFees()
                        ->where('is_separate_transaction', true)
                        ->get();

                    // Calculate the main tuition downpayment (excluding separate transaction fees)
                    $separateFeesTotal = $separateTransactionFees->sum('amount');
                    $mainTuitionDownpayment = max(0, ($record->studentTuition?->downpayment ?? 0) - $separateFeesTotal);

                    $formComponents = [
                        KeyValue::make('settlements')
                            ->label('Main Transaction Settlements')
                            ->columnSpanFull()
                            ->helperText(
                                'Enter the settlements for the main transaction (excluding separate transaction fees).'
                            )
                            ->default([
                                'registration_fee' => 0,
                                'tuition_fee' => $mainTuitionDownpayment,
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
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options(PaymentMethod::class)
                            ->default(PaymentMethod::Cash)
                            ->required(),
                        TextInput::make('invoicenumber')
                            ->label('Main Transaction Invoice Number')
                            ->required(),
                    ];

                    // Add separate transaction fields for each additional fee
                    if ($separateTransactionFees->isNotEmpty()) {
                        $formComponents[] = Section::make('Separate Transaction Fees')
                            ->description('These fees require separate payment transactions')
                            ->schema(
                                $separateTransactionFees->map(fn ($fee): Grid => Grid::make(2)
                                    ->schema([
                                        TextInput::make("separate_fee_{$fee->id}_amount")
                                            ->label("{$fee->fee_name} Amount")
                                            ->prefix('₱')
                                            ->numeric()
                                            ->default($fee->amount)
                                            ->readOnly(),
                                        TextInput::make("separate_fee_{$fee->id}_transaction")
                                            ->label("{$fee->fee_name} Transaction Number")
                                            ->placeholder('Enter transaction number')
                                            ->required(),
                                    ]))->toArray()
                            )
                            ->columnSpanFull();
                    }

                    return $formComponents;
                })
                ->visible(function (StudentEnrollment $record): bool {
                    $pipeline = app(EnrollmentPipelineService::class);

                    return $pipeline->isDepartmentVerified($record->status) && (Auth::user()->can(
                        'verify_by_cashier_guest::enrollment'
                    ) ||
                        Auth::user()->hasRole('super_admin'));
                })
                ->action(function (
                    StudentEnrollment $record,
                    array $data,
                    EnrollmentService $enrollmentService
                ) {
                    $success = $enrollmentService->verifyByCashier(
                        $record,
                        $data
                    );
                    if ($success) {
                        return redirect()->route(
                            'filament.admin.resources.students.index'
                        );
                    }
                    $this->halt();
                }),

            Action::make('verifyAsCashierNoReceipt')
                ->label('Enroll Without Receipt')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->modalHeading('Enroll Student Without Receipt')
                ->modalDescription(
                    'WARNING: Use this only for students who have lost their receipt but have confirmed payment. This action will enroll the student without creating a transaction record. Super Admin access only.'
                )
                ->form([
                    Placeholder::make('warning')
                        ->label('')
                        ->content('⚠️ This is an alternative enrollment method for lost receipts. The student will be marked as enrolled without soft-deleting the record, allowing proper analytics tracking.')
                        ->columnSpanFull(),
                    Textarea::make('remarks')
                        ->label('Verification Remarks')
                        ->placeholder('Enter details about payment confirmation, method of verification, etc.')
                        ->helperText('Required: Document how payment was confirmed without receipt')
                        ->required()
                        ->rows(4)
                        ->maxLength(1000)
                        ->columnSpanFull(),
                    Toggle::make('confirm_payment')
                        ->label('I confirm that payment has been verified through alternative means')
                        ->required()
                        ->accepted()
                        ->helperText('You must confirm that payment was verified before proceeding')
                        ->columnSpanFull(),
                ])
                ->visible(function (StudentEnrollment $record): bool {
                    $pipeline = app(EnrollmentPipelineService::class);

                    return $pipeline->isDepartmentVerified($record->status) && Auth::user()->hasRole('super_admin');
                })
                ->requiresConfirmation()
                ->modalIcon('heroicon-o-exclamation-triangle')
                ->modalSubmitActionLabel('Yes, Enroll Without Receipt')
                ->action(function (
                    StudentEnrollment $record,
                    array $data,
                    EnrollmentService $enrollmentService
                ): void {
                    $success = $enrollmentService->verifyByCashierWithoutReceipt(
                        $record,
                        $data
                    );
                    if ($success) {
                        Notification::make()
                            ->success()
                            ->title('Student Enrolled Successfully')
                            ->body('Student has been enrolled without receipt. Record remains active for analytics.')
                            ->send();
                        $this->refreshFormData(['status', 'remarks']);
                    } else {
                        $this->halt();
                    }
                }),

            // Dropdown for other actions
            ActionGroup::make([
                EditAction::make(),
                Action::make('enrollInSpecificClass')
                    ->label('Enroll in Specific Class')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading('Enroll in Specific Class')
                    ->modalDescription('Select a class to enroll the student in.')
                    ->form([
                        Select::make('class_id')
                            ->label('Class')
                            ->options(function (): array {
                                $record = $this->getRecord();
                                // Eager load subjectsEnrolled relationship with subject
                                $record->load('subjectsEnrolled.subject');
                                $subjectEnrollments = $record->subjectsEnrolled ?? collect();
                                $generalSettingsService = app(\App\Services\GeneralSettingsService::class);
                                $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
                                $currentSemester = $generalSettingsService->getCurrentSemester();

                                $options = [];
                                foreach ($subjectEnrollments as $subjectEnrollment) {
                                    // Access subject through the loaded relationship
                                    $subject = $subjectEnrollment->subject;
                                    if (! $subject) {
                                        continue;
                                    }

                                    $classes = \App\Models\Classes::query()
                                        ->where('school_year', $currentSchoolYear)
                                        ->where('semester', $currentSemester)
                                        ->whereJsonContains('course_codes', $subject->course_id)
                                        ->where(function ($query) use ($subject): void {
                                            $query->whereJsonContains('subject_ids', $subject->id)
                                                ->orWhereRaw('LOWER(TRIM(subject_code)) = LOWER(TRIM(?))', [$subject->code])
                                                ->orWhereRaw('LOWER(subject_code) LIKE LOWER(?)', ['%'.$subject->code.'%']);
                                        })
                                        ->get();

                                    foreach ($classes as $class) {
                                        $enrolledCount = \App\Models\ClassEnrollment::where('class_id', $class->id)->count();
                                        $maxSlots = $class->maximum_slots ?: 0;
                                        $availableSlots = $maxSlots > 0 ? $maxSlots - $enrolledCount : PHP_INT_MAX;
                                        $slotsInfo = $maxSlots > 0 ? " (Available: {$availableSlots}/{$maxSlots})" : ' (Unlimited)';
                                        $status = $maxSlots > 0 && $enrolledCount >= $maxSlots ? ' [FULL]' : '';

                                        $options[$class->id] = "{$subject->code} - Section {$class->section}{$slotsInfo}{$status}";
                                    }
                                }

                                return $options;
                            })
                            ->searchable()
                            ->required(),
                        Toggle::make('force_enrollment')
                            ->label('Force Enrollment')
                            ->helperText('Override maximum class size limits when enrolling')
                            ->default(false),
                    ])
                    ->action(function (array $data): void {
                        $record = $this->getRecord();
                        $student = $record->student;

                        try {
                            $class = \App\Models\Classes::find($data['class_id']);

                            if (! $class) {
                                Notification::make()
                                    ->danger()
                                    ->title('Class Not Found')
                                    ->body('The selected class could not be found.')
                                    ->send();

                                return;
                            }

                            // Check if already enrolled
                            $existingEnrollment = \App\Models\ClassEnrollment::where('class_id', $class->id)
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

                            // Check if class is full (unless forcing enrollment)
                            if (! $data['force_enrollment']) {
                                $enrolledCount = \App\Models\ClassEnrollment::where('class_id', $class->id)->count();
                                $maxSlots = $class->maximum_slots ?: 0;

                                if ($maxSlots > 0 && $enrolledCount >= $maxSlots) {
                                    Notification::make()
                                        ->danger()
                                        ->title('Class is Full')
                                        ->body('This class has reached its maximum capacity. Enable "Force Enrollment" to override.')
                                        ->send();

                                    return;
                                }
                            }

                            // Create the enrollment
                            \App\Models\ClassEnrollment::create([
                                'class_id' => $class->id,
                                'student_id' => $student->id,
                                'status' => true,
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Enrollment Successful')
                                ->body("Student has been enrolled in {$class->subject_code} - Section {$class->section}.")
                                ->send();

                            // Refresh the page
                            $this->dispatch('$refresh');
                        } catch (Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Enrollment Failed')
                                ->body('Error: '.$e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('retryClassEnrollment')
                    ->label('Retry Class Enrollment')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Retry Class Enrollment?')
                    ->modalDescription(
                        'This will attempt to re-enroll the student in all classes for the subjects in this enrollment. Force enrollment is enabled by default to override maximum class size limits.'
                    )
                    ->form([
                        Toggle::make(
                            'force_enrollment'
                        )
                            ->label('Force Enrollment')
                            ->helperText(
                                'Override maximum class size limits when enrolling'
                            )
                            ->default(true),
                    ])
                    ->action(function (
                        array $data,
                        StudentEnrollment $record
                    ): void {
                        $originalConfigValue = config(
                            'enrollment.force_enroll_when_full'
                        );
                        if ($data['force_enrollment']) {
                            config([
                                'enrollment.force_enroll_when_full' => true,
                            ]);
                        }
                        try {
                            $student = $record->student;
                            if (! $student) {
                                Notification::make()
                                    ->danger()
                                    ->title('Student Not Found')
                                    ->body(
                                        'The student associated with this enrollment could not be found.'
                                    )
                                    ->send();

                                return;
                            }
                            $student->autoEnrollInClasses($record->id);
                            Notification::make()
                                ->success()
                                ->title('Enrollment Retry Complete')
                                ->body(
                                    'The system has attempted to enroll the student in all classes. Check the notification for results.'
                                )
                                ->send();
                        } catch (Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Enrollment Retry Failed')
                                ->body('An error occurred: '.$e->getMessage())
                                ->send();
                        } finally {
                            if ($data['force_enrollment']) {
                                config([
                                    'enrollment.force_enroll_when_full' => $originalConfigValue,
                                ]);
                            }
                        }
                    }),
                Action::make('advancePipelineStep')
                    ->label('Advance Pipeline Step')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Advance Enrollment Pipeline')
                    ->modalDescription(function (StudentEnrollment $record): string {
                        $pipeline = app(EnrollmentPipelineService::class);
                        $nextStep = $pipeline->getNextStep($record->status);

                        if ($nextStep === null) {
                            return 'This enrollment is already at the final pipeline step.';
                        }

                        return "Advance this enrollment to \"{$nextStep['label']}\"?";
                    })
                    ->visible(function (StudentEnrollment $record): bool {
                        $pipeline = app(EnrollmentPipelineService::class);
                        $user = Auth::user();
                        if (! $user instanceof \App\Models\User) {
                            return false;
                        }

                        $nextStep = $pipeline->getNextStep($record->status);
                        if ($nextStep === null) {
                            return false;
                        }

                        if ($nextStep['status'] === $pipeline->getCashierVerifiedStatus()) {
                            return false;
                        }

                        return $pipeline->canUserPerformStep($user, $nextStep);
                    })
                    ->action(function (StudentEnrollment $record): void {
                        $pipeline = app(EnrollmentPipelineService::class);
                        $nextStep = $pipeline->getNextStep($record->status);
                        if ($nextStep === null) {
                            return;
                        }

                        if ($nextStep['status'] === $pipeline->getDepartmentVerifiedStatus()) {
                            $success = app(EnrollmentService::class)->verifyByHeadDept($record);
                            if (! $success) {
                                $this->halt();
                            }
                        } else {
                            $record->status = $nextStep['status'];
                            $record->save();

                            Notification::make()
                                ->success()
                                ->title('Pipeline Advanced')
                                ->body("Enrollment moved to \"{$nextStep['label']}\".")
                                ->send();
                        }

                        $this->refreshFormData(['status']);
                    }),

                Action::make('undoCashierVerification')
                    ->label('Undo Cashier Verification')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Undo Cashier Verification?')
                    ->modalDescription(
                        'This will restore the enrollment and revert the status to the previous workflow step. Financial transactions will NOT be automatically reversed and may require manual correction. Proceed?'
                    )
                    ->visible(function (StudentEnrollment $record): bool {
                        $currentRecord = StudentEnrollment::withTrashed()->find(
                            $record->id
                        );

                        $pipeline = app(EnrollmentPipelineService::class);

                        return ($currentRecord->trashed() ||
                            $pipeline->isCashierVerified($currentRecord->status)) &&
                            (Auth::user()->can(
                                'verify_by_cashier_guest::enrollment'
                            ) ||
                                Auth::user()->hasRole('super_admin'));
                    })
                    ->action(function (
                        StudentEnrollment $record,
                        EnrollmentService $enrollmentService
                    ): void {
                        $success = $enrollmentService->undoCashierVerification(
                            $record->id
                        );
                        if (! $success) {
                            $this->halt();
                        }
                        $this->refreshFormData([
                            'status',
                            'signature.cashier_signature',
                        ]);
                        $this->dispatch('refresh');
                    })
                    ->disabled(
                        fn (
                            StudentEnrollment $record
                        ): bool => ! $record->trashed() &&
                            ! app(EnrollmentPipelineService::class)->isCashierVerified($record->status)
                    ),

                Action::make('undoHeadDeptVerification')
                    ->label('Undo Head Dept Verification')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Undo Head Dept Verification?')
                    ->modalDescription(
                        'This will revert the status to "Pending" and remove the Head Dept signature. Proceed?'
                    )
                    ->visible(function (StudentEnrollment $record): bool {
                        $pipeline = app(EnrollmentPipelineService::class);

                        return $pipeline->isDepartmentVerified($record->status) &&
                            (Auth::user()->can(
                                'verify_by_head_dept_guest::enrollment'
                            ) ||
                                Auth::user()->hasRole('super_admin'));
                    })
                    ->action(function (
                        StudentEnrollment $record,
                        EnrollmentService $enrollmentService
                    ): void {
                        $success = $enrollmentService->undoHeadDeptVerification(
                            $record
                        );
                        if (! $success) {
                            $this->halt();
                        }
                        $this->refreshFormData([
                            'status',
                            'signature.depthead_signature',
                        ]);
                    }),

                Action::make('Resend Assessment')
                    ->label('Resend Assessment Notification')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->visible(function (StudentEnrollment $record): bool {
                        $pipeline = app(EnrollmentPipelineService::class);

                        return $pipeline->isCashierVerified($record->status) &&
                            ! empty($record->student->email) &&
                            (Auth::user()->can(
                                'verify_by_cashier_guest::enrollment'
                            ) ||
                                Auth::user()->hasRole('super_admin'));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Resend Assessment Notification')
                    ->modalDescription(
                        fn (
                            StudentEnrollment $record
                        ): string => "Are you sure you want to resend the assessment notification to {$record->student?->first_name} {$record->student?->last_name} ({$record->student?->email})?\n\nThis will generate a new PDF assessment form and send it via email."
                    )
                    ->modalSubmitActionLabel('Yes, Resend Assessment')
                    ->modalIcon('heroicon-o-envelope')
                    ->action(function (
                        StudentEnrollment $record,
                        EnrollmentService $enrollmentService
                    ): void {
                        $result = $enrollmentService->resendAssessmentNotification(
                            $record
                        );
                        if ($result['success']) {
                            Notification::make()
                                ->title('Assessment Resend Queued')
                                ->success()
                                ->body(
                                    "Assessment notification has been queued for {$result['student_name']}. The process is running in the background and you will receive a notification when completed."
                                )
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Assessment Resend Failed')
                                ->danger()
                                ->body($result['message'])
                                ->send();
                            $this->halt();
                        }
                    }),

                Action::make('create_new_assessment_pdf')
                    ->label('Create New Assessment PDF')
                    ->icon('heroicon-o-document-plus')
                    ->color('info')
                    ->visible(fn (StudentEnrollment $record): bool => app(EnrollmentPipelineService::class)->isCashierVerified($record->status))
                    ->requiresConfirmation()
                    ->modalHeading('Create New Assessment PDF')
                    ->modalDescription(
                        'This will generate a new assessment PDF for this student enrollment without overwriting the existing one. This is useful when there are issues with class schedules not showing up correctly in the PDF.'
                    )
                    ->action(function (StudentEnrollment $record): void {
                        try {
                            // Generate unique job ID
                            $jobId = uniqid('pdf_', true);

                            // Dispatch the PDF generation job with a flag to create new file
                            \App\Jobs\GenerateAssessmentPdfJob::dispatch(
                                $record,
                                $jobId,
                                true
                            );

                            Notification::make()
                                ->title('New PDF Generation Queued')
                                ->body(
                                    "A new assessment PDF generation has been queued and will be processed shortly. Super admin users will receive a notification when it's complete."
                                )
                                ->success()
                                ->send();

                            Log::info(
                                "New assessment PDF generation queued for enrollment {$record->id}",
                                [
                                    'enrollment_id' => $record->id,
                                    'student_id' => $record->student_id,
                                    'student_name' => $record->student?->full_name ??
                                        'Unknown',
                                    'create_new' => true,
                                    'job_id' => $jobId,
                                ]
                            );
                        } catch (Exception $e) {
                            Notification::make()
                                ->title('PDF Generation Failed')
                                ->body(
                                    'Failed to queue PDF generation: '.
                                        $e->getMessage()
                                )
                                ->danger()
                                ->send();

                            Log::error(
                                "Failed to queue new assessment PDF generation for enrollment {$record->id}",
                                [
                                    'enrollment_id' => $record->id,
                                    'error' => $e->getMessage(),
                                    'trace' => $e->getTraceAsString(),
                                ]
                            );
                        }
                    }),

                Action::make('View Assessment')
                    ->label('View Assessment')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(
                        fn (): string => route('assessment.download', [
                            'record' => $this->getRecord()->id,
                        ], false) // Use relative URL
                    )
                    ->openUrlInNewTab(false),
            ])
                ->label('More Options')
                ->icon('heroicon-m-ellipsis-vertical')
                ->size(Size::Small)
                ->color('gray')
                ->button(),
        ];
    }
}
