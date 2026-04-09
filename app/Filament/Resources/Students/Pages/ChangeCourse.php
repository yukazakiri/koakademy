<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Pages;

use App\Filament\Resources\Students\StudentResource;
use App\Models\Course;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use Exception;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ChangeCourse extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public Student $record;

    protected static string $resource = StudentResource::class;

    protected string $view = 'filament.resources.students.pages.change-course';

    public function mount(Student $record): void
    {
        $this->record = $record;

        $this->form->fill([
            'current_course_id' => $this->record->course_id,
            'new_course_id' => null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Current Course Information')
                    ->schema([
                        ViewComponent::make('filament.resources.students.components.course-info-display')
                            ->viewData([
                                'course' => $this->record->Course,
                            ]),

                        ViewComponent::make('filament.resources.students.components.subject-count-display')
                            ->viewData([
                                'count' => $this->record->subjectEnrolled()->count(),
                            ]),
                    ])
                    ->columns(2),

                Section::make('New Course Selection')
                    ->schema([
                        Select::make('new_course_id')
                            ->label('New Course')
                            ->options(fn (): array => Course::query()
                                ->where('is_active', true)
                                ->where('id', '!=', $this->record->course_id)
                                ->get()
                                ->mapWithKeys(function ($course): array {
                                    $label = sprintf(
                                        '%s [%s]%s',
                                        $course->title,
                                        $course->code,
                                        $course->school_year ? ' - '.$course->school_year : ''
                                    );

                                    return [$course->id => $label];
                                })
                                ->toArray())
                            ->required()
                            ->searchable()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set): void {
                                if (! $state) {
                                    return;
                                }

                                // Get the comparison data
                                $comparison = $this->compareSubjects((int) $state);
                                $set('subject_comparison', $comparison);
                            })
                            ->helperText('Select the new course for this student. Course code and school year are shown in brackets.'),
                    ]),

                Section::make('Subject Comparison Summary')
                    ->schema([
                        ViewComponent::make('filament.resources.students.components.comparison-summary')
                            ->viewData(function ($get): array {
                                $newCourseId = $get('new_course_id');
                                if (! $newCourseId) {
                                    return [
                                        'autoMatched' => 0,
                                        'requiresReview' => 0,
                                        'noCreditTransfer' => 0,
                                        'showPlaceholder' => true,
                                    ];
                                }

                                $comparison = $this->compareSubjects((int) $newCourseId);

                                return [
                                    'autoMatched' => count($comparison['auto_matched']),
                                    'requiresReview' => count($comparison['requires_review']),
                                    'noCreditTransfer' => count($comparison['no_credit_transfer']),
                                    'showPlaceholder' => false,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($get): bool => $get('new_course_id') !== null),

                Section::make('Subject Credit Transfer Details')
                    ->description('Review and approve subject transfers. Color coding: Green = Auto-matched, Yellow = Needs Review, Red = No Transfer')
                    ->schema([
                        Repeater::make('subject_transfers')
                            ->label('')
                            ->schema([
                                ViewComponent::make('filament.resources.students.components.subject-status-indicator')
                                    ->viewData(fn ($get): array => [
                                        'matchType' => $get('match_type') ?? 'none',
                                    ])
                                    ->columnSpanFull(),

                                Grid::make(2)
                                    ->schema([
                                        ViewComponent::make('filament.resources.students.components.subject-detail-display')
                                            ->viewData(fn ($get): array => [
                                                'title' => $get('old_subject_title') ?? '',
                                                'code' => $get('old_subject_code') ?? '',
                                                'units' => $get('old_subject_units') ?? 0,
                                            ]),

                                        Select::make('new_subject_id')
                                            ->label('→ Transfer to Subject (New Course)')
                                            ->options(fn ($get): array => $get('available_subjects') ?? [])
                                            ->searchable()
                                            ->placeholder(fn ($get): string => match ($get('match_type')) {
                                                'auto' => '✓ Auto-matched - No action needed',
                                                'review' => 'Select equivalent subject...',
                                                'none' => 'No matching subjects available',
                                                default => 'Select a subject',
                                            })
                                            ->disabled(fn ($get): bool => $get('match_type') === 'auto' || $get('match_type') === 'none')
                                            ->helperText(fn ($get): string => match ($get('match_type')) {
                                                'auto' => '✓ Subject codes match exactly - will be transferred automatically',
                                                'review' => '⚠ Please select an equivalent subject from the new course, or leave empty to skip',
                                                'none' => '✗ No matching subjects found in the new course for this year level',
                                                default => '',
                                            }),
                                    ]),

                                Textarea::make('transfer_notes')
                                    ->label('Transfer Notes (Optional)')
                                    ->placeholder('Add notes about this subject transfer (e.g., "Same content, different code", "Approved by program head")')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                // Hidden fields to store data
                                Select::make('old_subject_enrollment_id')
                                    ->hidden()
                                    ->dehydrated(),
                                Select::make('old_subject_id')
                                    ->hidden()
                                    ->dehydrated(),
                                Select::make('old_subject_code')
                                    ->hidden()
                                    ->dehydrated(),
                                Select::make('old_subject_title')
                                    ->hidden()
                                    ->dehydrated(),
                                Select::make('old_subject_units')
                                    ->hidden()
                                    ->dehydrated(),
                                Select::make('match_type')
                                    ->hidden()
                                    ->dehydrated(),
                                Select::make('available_subjects')
                                    ->hidden()
                                    ->dehydrated(),
                            ])
                            ->default(function ($get): array {
                                $newCourseId = $get('new_course_id');
                                if (! $newCourseId) {
                                    return [];
                                }

                                $comparison = $this->compareSubjects((int) $newCourseId);

                                return $this->buildTransferArray($comparison);
                            })
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($get): bool => $get('new_course_id') !== null),

                Section::make('⚠️ Confirmation Required')
                    ->schema([
                        Checkbox::make('confirm_course_change')
                            ->label('I confirm this course change and subject credit transfers')
                            ->helperText('This will update the student\'s course and create credited subject enrollments for transferred subjects.')
                            ->required()
                            ->accepted(),
                    ])
                    ->description('This operation will permanently change the student\'s course. Subject enrollments will be updated accordingly.')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->visible(fn ($get): bool => $get('new_course_id') !== null),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        DB::beginTransaction();

        try {
            $oldCourseId = $this->record->course_id;
            $newCourseId = (int) $data['new_course_id'];
            $oldCourse = Course::query()->find($oldCourseId);
            $newCourse = Course::query()->find($newCourseId);

            // Update student's course
            $this->record->update([
                'course_id' => $newCourseId,
            ]);

            // Process subject transfers
            $transferredCount = 0;
            $skippedCount = 0;

            foreach ($data['subject_transfers'] as $transfer) {
                if (empty($transfer['new_subject_id'])) {
                    $skippedCount++;

                    continue;
                }

                // Get the original subject enrollment
                $oldEnrollment = SubjectEnrollment::query()->find($transfer['old_subject_enrollment_id']);
                if (! $oldEnrollment) {
                    continue;
                }

                // Create a credited subject enrollment for the new subject
                SubjectEnrollment::query()->create([
                    'student_id' => $this->record->id,
                    'subject_id' => (int) $transfer['new_subject_id'],
                    'academic_year' => $oldEnrollment->academic_year,
                    'school_year' => $oldEnrollment->school_year,
                    'semester' => $oldEnrollment->semester,
                    'enrollment_id' => $oldEnrollment->enrollment_id,
                    'is_credited' => true,
                    'credited_subject_id' => $oldEnrollment->subject_id,
                    'classification' => 'credited',
                    'school_name' => $oldCourse?->title ?? 'Previous Course',
                    'external_subject_code' => $transfer['old_subject_code'],
                    'external_subject_title' => $transfer['old_subject_title'],
                    'external_subject_units' => (int) $transfer['old_subject_units'],
                    'grade' => $oldEnrollment->grade,
                    'remarks' => $transfer['transfer_notes'] ?? 'Credited from course change',
                ]);

                // Mark the old enrollment as credited
                $oldEnrollment->update([
                    'is_credited' => true,
                    'remarks' => 'Credited to new course: '.$newCourse?->title,
                ]);

                $transferredCount++;
            }

            DB::commit();

            // Send success notification
            Notification::make()
                ->title('Course Changed Successfully')
                ->body(sprintf(
                    'Student course changed from %s to %s. %d subject(s) credited, %d subject(s) not transferred.',
                    $oldCourse?->title ?? 'Unknown',
                    $newCourse?->title ?? 'Unknown',
                    $transferredCount,
                    $skippedCount
                ))
                ->success()
                ->duration(8000)
                ->send();

            Log::info('Student course changed', [
                'student_id' => $this->record->id,
                'old_course_id' => $oldCourseId,
                'new_course_id' => $newCourseId,
                'transferred_subjects' => $transferredCount,
                'skipped_subjects' => $skippedCount,
            ]);

            // Redirect back to student view page
            $this->redirect(StudentResource::getUrl('view', ['record' => $this->record]));
        } catch (Exception $exception) {
            DB::rollBack();

            Notification::make()
                ->title('Course Change Failed')
                ->body('Error: '.$exception->getMessage())
                ->danger()
                ->duration(10000)
                ->send();

            Log::error('Course change failed', [
                'student_id' => $this->record->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            throw $exception;
        }
    }

    public function getTitle(): string
    {
        return 'Change Course';
    }

    public function getHeading(): string
    {
        return 'Change Course for '.$this->record->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Change Course')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm Course Change')
                ->modalDescription('Are you sure you want to change this student\'s course? This action will update subject enrollments accordingly.')
                ->action('save'),
        ];
    }

    /**
     * Compare subjects between old and new course
     *
     * @return array{auto_matched: array, requires_review: array, no_credit_transfer: array}
     */
    private function compareSubjects(int $newCourseId): array
    {
        // Get all enrolled subjects for this student
        $enrolledSubjects = $this->record->subjectEnrolled()
            ->with('subject')
            ->get();

        // Get all subjects in the new course
        $newCourseSubjects = Subject::query()
            ->where('course_id', $newCourseId)
            ->get()
            ->keyBy('code');

        $autoMatched = [];
        $requiresReview = [];
        $noCreditTransfer = [];

        foreach ($enrolledSubjects as $enrollment) {
            $oldSubject = $enrollment->subject;
            if (! $oldSubject) {
                continue;
            }

            // Try to find exact match by subject code
            $matchedSubject = $newCourseSubjects->get($oldSubject->code);

            if ($matchedSubject) {
                // Auto-matched by exact code
                $autoMatched[] = [
                    'enrollment' => $enrollment,
                    'old_subject' => $oldSubject,
                    'new_subject' => $matchedSubject,
                ];
            } else {
                // No exact match - requires manual review
                // Get all available subjects in new course as options
                $availableSubjects = Subject::query()
                    ->where('course_id', $newCourseId)
                    ->where('academic_year', $oldSubject->academic_year)
                    ->get();

                if ($availableSubjects->isNotEmpty()) {
                    $requiresReview[] = [
                        'enrollment' => $enrollment,
                        'old_subject' => $oldSubject,
                        'available_subjects' => $availableSubjects,
                    ];
                } else {
                    $noCreditTransfer[] = [
                        'enrollment' => $enrollment,
                        'old_subject' => $oldSubject,
                    ];
                }
            }
        }

        return [
            'auto_matched' => $autoMatched,
            'requires_review' => $requiresReview,
            'no_credit_transfer' => $noCreditTransfer,
        ];
    }

    /**
     * Build the transfer array for the repeater
     */
    private function buildTransferArray(array $comparison): array
    {
        $transfers = [];

        // Add auto-matched subjects
        foreach ($comparison['auto_matched'] as $match) {
            $transfers[] = [
                'old_subject_enrollment_id' => $match['enrollment']->id,
                'old_subject_id' => $match['old_subject']->id,
                'old_subject_code' => $match['old_subject']->code,
                'old_subject_title' => $match['old_subject']->title,
                'old_subject_units' => $match['old_subject']->units,
                'new_subject_id' => $match['new_subject']->id,
                'match_type' => 'auto',
                'available_subjects' => [
                    $match['new_subject']->id => $match['new_subject']->title.' ('.$match['new_subject']->code.')',
                ],
            ];
        }

        // Add subjects requiring review
        foreach ($comparison['requires_review'] as $review) {
            $availableOptions = [];
            foreach ($review['available_subjects'] as $subject) {
                $availableOptions[$subject->id] = $subject->title.' ('.$subject->code.') - '.$subject->units.' units';
            }

            $transfers[] = [
                'old_subject_enrollment_id' => $review['enrollment']->id,
                'old_subject_id' => $review['old_subject']->id,
                'old_subject_code' => $review['old_subject']->code,
                'old_subject_title' => $review['old_subject']->title,
                'old_subject_units' => $review['old_subject']->units,
                'new_subject_id' => null,
                'match_type' => 'review',
                'available_subjects' => $availableOptions,
            ];
        }

        // Add subjects with no credit transfer
        foreach ($comparison['no_credit_transfer'] as $noTransfer) {
            $transfers[] = [
                'old_subject_enrollment_id' => $noTransfer['enrollment']->id,
                'old_subject_id' => $noTransfer['old_subject']->id,
                'old_subject_code' => $noTransfer['old_subject']->code,
                'old_subject_title' => $noTransfer['old_subject']->title,
                'old_subject_units' => $noTransfer['old_subject']->units,
                'new_subject_id' => null,
                'match_type' => 'none',
                'available_subjects' => [],
            ];
        }

        return $transfers;
    }
}
