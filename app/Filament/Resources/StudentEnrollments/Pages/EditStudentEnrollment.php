<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Pages;

use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Models\StudentTuition;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

final class EditStudentEnrollment extends EditRecord
{
    protected static string $resource = StudentEnrollmentResource::class;

    private array $tuitionData = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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
            'total_balance' => $data['total_balance'] ?? 0,
        ];

        // Store tuition data temporarily
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

    protected function afterSave(): void
    {
        // Update or create the student tuition record
        if ($this->tuitionData !== []) {
            StudentTuition::updateOrCreate(
                ['enrollment_id' => $this->record->id],
                $this->tuitionData
            );
        }
    }
}
