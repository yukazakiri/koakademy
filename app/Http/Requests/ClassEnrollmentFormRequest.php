<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClassEnrollmentFormRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->route('id') !== null;

        return [
            'class_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'exists:classes,id',
            ],
            'student_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'exists:students,id',
            ],
            'completion_date' => [
                'nullable',
                'date',
            ],
            'status' => [
                'nullable',
                'in:enrolled,completed,dropped,withdrawn,incomplete',
            ],
            'remarks' => [
                'nullable',
                'string',
            ],
            'prelim_grade' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'midterm_grade' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'finals_grade' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'total_average' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'is_grades_finalized' => [
                'nullable',
                'boolean',
            ],
            'is_grades_verified' => [
                'nullable',
                'boolean',
            ],
            'verified_by' => [
                'nullable',
                'exists:users,id',
            ],
            'verified_at' => [
                'nullable',
                'date',
            ],
            'verification_notes' => [
                'nullable',
                'string',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'class_id.required' => 'The class ID is required.',
            'class_id.exists' => 'The selected class is invalid.',
            'student_id.required' => 'The student ID is required.',
            'student_id.exists' => 'The selected student is invalid.',
            'completion_date.date' => 'The completion date must be a valid date.',
            'status.in' => 'The selected status is invalid. Must be: enrolled, completed, dropped, withdrawn, or incomplete.',
            'prelim_grade.numeric' => 'The prelim grade must be a number.',
            'prelim_grade.min' => 'The prelim grade must be at least 0.',
            'prelim_grade.max' => 'The prelim grade must not be greater than 100.',
            'midterm_grade.numeric' => 'The midterm grade must be a number.',
            'midterm_grade.min' => 'The midterm grade must be at least 0.',
            'midterm_grade.max' => 'The midterm grade must not be greater than 100.',
            'finals_grade.numeric' => 'The finals grade must be a number.',
            'finals_grade.min' => 'The finals grade must be at least 0.',
            'finals_grade.max' => 'The finals grade must not be greater than 100.',
            'total_average.numeric' => 'The total average must be a number.',
            'total_average.min' => 'The total average must be at least 0.',
            'total_average.max' => 'The total average must not be greater than 100.',
            'is_grades_finalized.boolean' => 'The grades finalized field must be true or false.',
            'is_grades_verified.boolean' => 'The grades verified field must be true or false.',
            'verified_by.exists' => 'The verifier must be a valid user.',
            'verified_at.date' => 'The verified date must be a valid date.',
            'verification_notes.string' => 'The verification notes must be a string.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'class_id' => 'class ID',
            'student_id' => 'student ID',
            'prelim_grade' => 'preliminary grade',
            'midterm_grade' => 'midterm grade',
            'finals_grade' => 'finals grade',
            'total_average' => 'total average',
            'is_grades_finalized' => 'grades finalized',
            'is_grades_verified' => 'grades verified',
            'verified_by' => 'verified by',
            'verified_at' => 'verified at',
            'verification_notes' => 'verification notes',
        ];
    }
}
