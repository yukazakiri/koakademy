<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreEnrollmentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'student_type' => ['required', 'in:college,tesda'],
            'department' => ['required_if:student_type,college', 'exists:departments,code'],
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'academic_year' => ['required_if:student_type,college', 'integer', 'in:1,2,3,4'],

            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:20'],

            'birth_date' => ['required', 'date', 'before:today'],
            'gender' => ['required', 'in:male,female'],
            'civil_status' => ['nullable', 'string', 'max:50'],
            'nationality' => ['required', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],

            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['required', 'string', 'max:2000'],

            'contacts.personal_contact' => ['nullable', 'string', 'max:30'],
            'contacts.emergency_contact_name' => ['required', 'string', 'max:255'],
            'contacts.emergency_contact_phone' => ['required', 'string', 'max:30'],
            'contacts.emergency_contact_relationship' => ['nullable', 'string', 'max:100'],

            'parents.father_name' => ['nullable', 'string', 'max:255'],
            'parents.father_contact' => ['nullable', 'string', 'max:30'],
            'parents.mother_name' => ['nullable', 'string', 'max:255'],
            'parents.mother_contact' => ['nullable', 'string', 'max:30'],
            'parents.guardian_name' => ['required', 'string', 'max:255'],
            'parents.guardian_relationship' => ['required', 'string', 'max:100'],
            'parents.guardian_contact' => ['required', 'string', 'max:30'],
            'parents.family_address' => ['nullable', 'string', 'max:2000'],

            'education.elementary_school' => ['nullable', 'string', 'max:255'],
            'education.elementary_year_graduated' => ['nullable', 'string', 'max:50'],
            'education.high_school' => ['nullable', 'string', 'max:255'],
            'education.high_school_year_graduated' => ['nullable', 'string', 'max:50'],
            'education.senior_high_school' => ['nullable', 'string', 'max:255'],
            'education.senior_high_year_graduated' => ['nullable', 'string', 'max:50'],
            'education.vocational_school' => ['nullable', 'string', 'max:255'],
            'education.vocational_course' => ['nullable', 'string', 'max:255'],
            'education.vocational_year_graduated' => ['nullable', 'string', 'max:50'],

            // Document uploads (optional)
            'documents' => ['nullable', 'array', 'max:20'],
            'documents.*.type' => ['required_with:documents', 'string', 'max:100'],
            'documents.*.file' => ['required_with:documents', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],

            'consent' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_type.required' => 'Please choose whether the applicant is a College or TESDA student.',
            'student_type.in' => 'Please choose a valid student type.',
            'department.required_if' => 'Please select a department for College applicants.',
            'department.exists' => 'Please select a valid department.',
            'academic_year.required_if' => 'Please select the applicant\'s year level (1st to 4th year).',
            'academic_year.in' => 'Please select a valid year level (1 to 4).',
            'course_id.required' => 'Please select a course/program.',
            'course_id.exists' => 'The selected course/program is invalid.',
            'birth_date.before' => 'Birth date must be a valid date in the past.',
            'contacts.emergency_contact_name.required' => 'Emergency contact name is required.',
            'contacts.emergency_contact_phone.required' => 'Emergency contact phone is required.',
            'parents.guardian_name.required' => 'Guardian name is required.',
            'parents.guardian_relationship.required' => 'Guardian relationship is required.',
            'parents.guardian_contact.required' => 'Guardian contact number is required.',
            'documents.*.file.max' => 'Each document must be no larger than 10MB.',
            'documents.*.file.mimes' => 'Documents must be JPG, PNG, WebP, or PDF files.',
            'consent.accepted' => 'You must confirm that the information is accurate and you agree to the data privacy notice.',
        ];
    }
}
