<?php

declare(strict_types=1);

namespace App\Filament\Resources\Students\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateStudentRequest extends FormRequest
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
            'first_name' => 'required',
            'last_name' => 'required',
            'middle_name' => 'required',
            'gender' => 'required',
            'birth_date' => 'required|date',
            'age' => 'required',
            'address' => 'required',
            'contacts' => 'required|string',
            'course_id' => 'required',
            'academic_year' => 'required',
            'email' => 'required',
            'remarks' => 'required|string',
            'profile_url' => 'required',
            'student_contact_id' => 'required',
            'student_parent_info' => 'required',
            'student_education_id' => 'required',
            'student_personal_id' => 'required',
            'document_location_id' => 'required',
            'deleted_at' => 'required',
            'student_id' => 'required',
            'status' => 'required',
            'clearance_status' => 'required',
            'year_graduated' => 'required',
            'special_order' => 'required',
            'issued_date' => 'required|date',
            'subject_enrolled' => 'required',
            'user_id' => 'required',
            'institution_id' => 'required',
            'lrn' => 'required',
            'student_type' => 'required',
            'ethnicity' => 'required',
            'city_of_origin' => 'required',
            'province_of_origin' => 'required',
            'region_of_origin' => 'required',
            'is_indigenous_person' => 'required',
            'indigenous_group' => 'required',
            'withdrawal_date' => 'required|date',
            'withdrawal_reason' => 'required|string',
            'attrition_category' => 'required',
            'dropout_date' => 'required|date',
            'employment_status' => 'required',
            'employer_name' => 'required',
            'job_position' => 'required',
            'employment_date' => 'required|date',
            'employed_by_institution' => 'required',
            'scholarship_type' => 'required',
            'scholarship_details' => 'required|string',
        ];
    }
}
