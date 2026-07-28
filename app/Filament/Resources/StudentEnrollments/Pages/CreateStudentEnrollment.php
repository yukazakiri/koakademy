<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Pages;

use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Jobs\GenerateAssessmentPdfJob;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\User;
use App\Services\EnrollmentBillingService;
use Exception;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Override;

final class CreateStudentEnrollment extends CreateRecord
{
    #[Override]
    protected static string $resource = StudentEnrollmentResource::class;

    private array $tuitionData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get course_id from the selected student if not present
        if (empty($data['course_id']) && ! empty($data['student_id'])) {
            $student = \App\Models\Student::find($data['student_id']);
            if ($student) {
                $data['course_id'] = $student->course_id;
            }
        }

        // Extract tuition-related fields that should not go into student_enrollment table
        $tuitionData = [
            'discount' => $data['discount'] ?? 0,
            'total_lectures' => $data['total_lectures'] ?? 0,
            'total_laboratory' => $data['total_laboratory'] ?? 0,
            'total_tuition' => $data['Total_Tuition'] ?? 0,
            'total_miscelaneous_fees' => $data['miscellaneous'] ?? 3500,
            'overall_tuition' => $data['overall_total'] ?? 0,
            'downpayment' => $data['downpayment'] ?? 0,
            'total_balance' => $data['overall_total'] ?? ($data['total_balance'] ?? 0),
        ];

        // Store tuition data temporarily so we can create it after enrollment is created
        $this->tuitionData = $tuitionData;

        // Remove all tuition-related fields from enrollment data
        // Note: downpayment stays because it's also in student_enrollment table
        unset(
            $data['guest_email'],
            $data['full_name'],
            $data['is_manually_modified'],
            $data['discount'],
            $data['original_lecture_amount'],
            $data['is_overall_manually_modified'],
            $data['original_overall_amount'],
            $data['total_lectures'],
            $data['total_laboratory'],
            $data['Total_Tuition'],
            $data['miscellaneous'],
            $data['additional_fees_trigger'],
            $data['overall_total'],
            $data['total_balance']
        );

        return $data;
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        $raw = $this->form->getRawState();
        $subjects = array_values(array_filter($raw['subjectsEnrolled'] ?? [], is_array(...)));
        $additionalFees = array_values(array_filter($raw['additionalFees'] ?? [], is_array(...)));
        $actor = Auth::user();
        $enrollment = app(EnrollmentWorkflowCoordinator::class)->submit(new EnrollmentSubmissionData(
            enrollmentAttributes: $data,
            subjects: $subjects,
            classAssignments: collect($subjects)
                ->filter(fn (array $subject): bool => ! empty($subject['class_id']))
                ->map(fn (array $subject): array => [
                    'subject_id' => (int) $subject['subject_id'],
                    'class_id' => (int) $subject['class_id'],
                ])
                ->values()
                ->all(),
            additionalFees: $additionalFees,
            billingOverrides: [
                'discount_percentage' => $this->tuitionData['discount'] ?? 0,
                'miscellaneous_fee' => $this->tuitionData['total_miscelaneous_fees'] ?? 3500,
                'downpayment' => $this->tuitionData['downpayment'] ?? 0,
                'overall_total' => $this->tuitionData['overall_tuition'] ?? null,
                'is_overall_manually_modified' => (bool) ($raw['is_overall_manually_modified'] ?? false),
            ],
            channel: 'administrator',
            idempotencyKey: (string) Str::uuid(),
            actor: $actor instanceof User ? $actor : null,
        ));

        if ($enrollment->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
            $this->data['subjectsEnrolled'] = [];
            $this->data['additionalFees'] = [];
        }

        return $enrollment;
    }

    protected function afterCreate(): void
    {
        if ($this->record->workflow_runtime === StudentEnrollment::WorkflowRuntimePolicyV1) {
            return;
        }

        // Create the student tuition record with the extracted data
        if (isset($this->tuitionData)) {
            $tuition = StudentTuition::create([
                'enrollment_id' => $this->record->id,
                'student_id' => $this->record->student_id,
                ...$this->tuitionData,
            ]);

            app(EnrollmentBillingService::class)->syncTuitionBalance(
                $tuition,
                (float) ($this->tuitionData['downpayment'] ?? 0)
            );
        }

        // Dispatch PDF generation job
        try {
            Log::info('Dispatching PDF generation job for enrollment', [
                'enrollment_id' => $this->record->id,
                'student_id' => $this->record->student_id,
            ]);

            GenerateAssessmentPdfJob::dispatch($this->record, createNewFile: false);

            Log::info('PDF generation job dispatched successfully', [
                'enrollment_id' => $this->record->id,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to dispatch PDF generation job', [
                'enrollment_id' => $this->record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
