<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Transformers;

use App\Models\Student;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Student $resource
 */
final class StudentTransformer extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->resource->id,
            'student_id' => $this->resource->student_id,
            'lrn' => $this->resource->lrn,
            'student_type' => $this->resource->student_type?->value,

            // Basic Information
            'basic_information' => [
                'full_name' => $this->resource->full_name,
                'first_name' => $this->resource->first_name,
                'middle_name' => $this->resource->middle_name,
                'last_name' => $this->resource->last_name,
                'suffix' => $this->resource->suffix,
                'email' => $this->resource->email,
                'phone' => $this->resource->phone,
                'birth_date' => $this->resource->birth_date?->format('Y-m-d'),
                'age' => $this->resource->age,
                'gender' => $this->resource->gender,
                'civil_status' => $this->resource->civil_status,
                'nationality' => $this->resource->nationality,
                'religion' => $this->resource->religion,
            ],

            // Academic Information
            'academic_information' => [
                'academic_year' => $this->resource->academic_year,
                'formatted_academic_year' => $this->resource->formatted_academic_year,
                'course' => $this->when($this->resource->Course, [
                    'id' => $this->resource->Course?->id,
                    'code' => $this->resource->Course?->code,
                    'name' => $this->resource->Course?->name,
                    'description' => $this->resource->Course?->description,
                ]),
                'status' => $this->resource->status?->value,
            ],

            // Address Information
            'address_information' => [
                'current_address' => $this->resource->personalInfo?->current_adress,
                'permanent_address' => $this->resource->personalInfo?->permanent_address,
                'city_of_origin' => $this->resource->city_of_origin,
                'province_of_origin' => $this->resource->province_of_origin,
                'region_of_origin' => $this->resource->region_of_origin,
            ],

            // Contact Information
            'contact_information' => [
                'personal_contact' => $this->resource->studentContactsInfo?->personal_contact,
                'emergency_contact_name' => $this->resource->studentContactsInfo?->emergency_contact_name,
                'emergency_contact_phone' => $this->resource->studentContactsInfo?->emergency_contact_phone,
                'emergency_contact_address' => $this->resource->studentContactsInfo?->emergency_contact_address,
                'facebook_contact' => $this->resource->studentContactsInfo?->facebook_contact,
            ],

            // Parent Information
            'parent_information' => [
                'fathers_name' => $this->resource->studentParentInfo?->fathers_name,
                'fathers_occupation' => $this->resource->studentParentInfo?->fathers_occupation,
                'fathers_contact' => $this->resource->studentParentInfo?->fathers_contact,
                'mothers_name' => $this->resource->studentParentInfo?->mothers_name,
                'mothers_occupation' => $this->resource->studentParentInfo?->mothers_occupation,
                'mothers_contact' => $this->resource->studentParentInfo?->mothers_contact,
            ],

            // Education Information
            'education_information' => [
                'elementary_school' => $this->resource->studentEducationInfo?->elementary_school,
                'elementary_graduate_year' => $this->resource->studentEducationInfo?->elementary_graduate_year,
                'elementary_school_address' => $this->resource->studentEducationInfo?->elementary_school_address,
                'senior_high_name' => $this->resource->studentEducationInfo?->senior_high_name,
                'senior_high_graduate_year' => $this->resource->studentEducationInfo?->senior_high_graduate_year,
                'senior_high_address' => $this->resource->studentEducationInfo?->senior_high_address,
            ],

            // Clearance Information
            'clearance_information' => [
                'current_clearance' => [
                    'status' => $this->resource->hasCurrentClearance() ? 'Cleared' : 'Not Cleared',
                    'is_cleared' => $this->resource->hasCurrentClearance(),
                    'cleared_by' => $this->resource->getCurrentClearanceModel()?->cleared_by,
                    'cleared_at' => $this->resource->getCurrentClearanceModel()?->cleared_at?->format('F j, Y g:i A'),
                    'remarks' => $this->resource->getCurrentClearanceModel()?->remarks,
                    'academic_year' => $this->resource->getCurrentClearanceModel()?->academic_year,
                    'semester' => $this->resource->getCurrentClearanceModel()?->semester,
                    'formatted_semester' => $this->resource->getCurrentClearanceModel()?->formatted_semester,
                ],
                'previous_clearance' => $this->getPreviousClearanceData(),
                'clearance_history' => $this->when($this->resource->clearances, fn () => $this->resource->clearances->map(fn ($clearance): array => [
                    'academic_year' => $clearance->academic_year,
                    'semester' => $clearance->semester,
                    'formatted_semester' => $clearance->formatted_semester,
                    'is_cleared' => $clearance->is_cleared,
                    'status' => $clearance->is_cleared ? 'Cleared' : 'Not Cleared',
                    'cleared_by' => $clearance->cleared_by,
                    'cleared_at' => $clearance->cleared_at?->format('M j, Y g:i A'),
                    'remarks' => $clearance->remarks,
                ])),
            ],

            // Tuition Information
            'tuition_information' => [
                'total_tuition' => $this->resource->getCurrentTuitionModel()?->formatted_total_tuition,
                'lecture_fees' => $this->resource->getCurrentTuitionModel()?->formatted_total_lectures,
                'laboratory_fees' => $this->resource->getCurrentTuitionModel()?->formatted_total_laboratory,
                'miscellaneous_fees' => $this->resource->getCurrentTuitionModel()?->formatted_total_miscelaneous_fees,
                'overall_tuition' => $this->resource->getCurrentTuitionModel()?->formatted_overall_tuition,
                'downpayment' => $this->resource->getCurrentTuitionModel()?->formatted_downpayment,
                'balance' => $this->resource->getCurrentTuitionModel()?->formatted_total_balance,
                'discount' => $this->resource->getCurrentTuitionModel()?->formatted_discount,
                'payment_status' => $this->resource->getCurrentTuitionModel()?->payment_status,
                'semester' => $this->resource->getCurrentTuitionModel()?->formatted_semester,
                'academic_year' => $this->resource->getCurrentTuitionModel()?->academic_year,
            ],

            // Current Enrolled Subjects
            'current_enrolled_subjects' => $this->when($this->resource->subjectEnrolled, fn () => $this->resource->subjectEnrolled->map(fn ($enrollment): array => [
                'subject_code' => $enrollment->subject?->code,
                'subject_title' => $enrollment->subject?->title,
                'units' => $enrollment->subject?->units,
                'section' => $enrollment->class?->section,
                'grade' => $enrollment->grade,
                'academic_year' => $enrollment->academic_year,
                'semester' => $enrollment->semester,
            ])),

            // Document Location
            'documents' => $this->resource->DocumentLocation?->toResolvedDocumentArray(),

            // Demographic & Statistical Information
            'demographic_information' => [
                'ethnicity' => $this->resource->ethnicity,
                'is_indigenous_person' => $this->resource->is_indigenous_person,
                'indigenous_group' => $this->resource->indigenous_group,
            ],

            // Scholarship Information
            'scholarship_information' => [
                'scholarship_type' => $this->resource->scholarship_type?->value,
                'scholarship_details' => $this->resource->scholarship_details,
            ],

            // Employment Information (for graduates)
            'employment_information' => [
                'employment_status' => $this->resource->employment_status?->value,
                'employer_name' => $this->resource->employer_name,
                'job_position' => $this->resource->job_position,
                'employment_date' => $this->resource->employment_date?->format('Y-m-d'),
                'employed_by_institution' => $this->resource->employed_by_institution,
            ],

            // Attrition Information
            'attrition_information' => [
                'withdrawal_date' => $this->resource->withdrawal_date?->format('Y-m-d'),
                'withdrawal_reason' => $this->resource->withdrawal_reason,
                'attrition_category' => $this->resource->attrition_category?->value,
                'dropout_date' => $this->resource->dropout_date?->format('Y-m-d'),
            ],

            // Timestamps
            'created_at' => format_timestamp($this->resource->created_at),
            'updated_at' => format_timestamp($this->resource->updated_at),
            'deleted_at' => format_timestamp($this->resource->deleted_at),
        ];
    }

    /**
     * Get previous clearance data with validation
     */
    private function getPreviousClearanceData(): array
    {
        $validation = $this->resource->validateEnrollmentClearance();
        $previous = $this->resource->getPreviousAcademicPeriod();

        return [
            'status' => $validation['clearance'] ? ($validation['allowed'] ? 'Cleared' : 'Not Cleared') : 'No Record',
            'allowed' => $validation['allowed'],
            'message' => $validation['message'],
            'academic_period' => "{$previous['academic_year']} - Semester {$previous['semester']}",
            'academic_year' => $previous['academic_year'],
            'semester' => $previous['semester'],
        ];
    }
}
