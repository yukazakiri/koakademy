<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'string', 'in:male,female'],
            'birth_date' => ['sometimes', 'date'],
            'age' => ['sometimes', 'integer', 'min:0'],
            'address' => ['nullable', 'string', 'max:500'],
            'contacts' => ['nullable', 'array'],
            'course_id' => ['sometimes', 'integer'],
            'academic_year' => ['sometimes', 'integer', 'min:1', 'max:6'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'emergency_contact' => ['nullable', 'array'],
            'remarks' => ['nullable', 'string'],
            'profile_url' => ['nullable', 'url', 'max:500'],
            'student_contact_id' => ['nullable', 'integer'],
            'student_parent_info' => ['nullable', 'integer'],
            'student_education_id' => ['nullable', 'integer'],
            'student_personal_id' => ['nullable', 'integer'],
            'document_location_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:enrolled,withdrawn,graduated,applicant'],
            'clearance_status' => ['sometimes', 'string', 'in:pending,approved,rejected'],
            'year_graduated' => ['nullable', 'integer'],
            'special_order' => ['nullable', 'string'],
            'issued_date' => ['nullable', 'date'],
            'subject_enrolled' => ['nullable', 'array'],
            'user_id' => ['nullable', 'integer'],
            'institution_id' => ['nullable', 'string'],
            'lrn' => ['nullable', 'string', 'max:20'],
            'student_type' => ['nullable', 'string'],
            'ethnicity' => ['nullable', 'string', 'max:100'],
            'city_of_origin' => ['nullable', 'string', 'max:100'],
            'province_of_origin' => ['nullable', 'string', 'max:100'],
            'region_of_origin' => ['nullable', 'string', 'max:100'],
            'is_indigenous_person' => ['nullable', 'boolean'],
            'indigenous_group' => ['nullable', 'string', 'max:100'],
            'withdrawal_date' => ['nullable', 'date'],
            'withdrawal_reason' => ['nullable', 'string'],
            'attrition_category' => ['nullable', 'string'],
            'dropout_date' => ['nullable', 'date'],
            'employment_status' => ['nullable', 'string'],
            'employer_name' => ['nullable', 'string', 'max:255'],
            'job_position' => ['nullable', 'string', 'max:255'],
            'employment_date' => ['nullable', 'date'],
            'employed_by_institution' => ['nullable', 'boolean'],
            'scholarship_type' => ['nullable', 'string', 'max:100'],
            'scholarship_details' => ['nullable', 'string'],
        ];
    }
}
