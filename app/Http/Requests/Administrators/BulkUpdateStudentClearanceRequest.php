<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class BulkUpdateStudentClearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'is_cleared' => ['required', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'Select at least one student.',
            'student_ids.array' => 'Selected students must be a valid list.',
            'student_ids.min' => 'Select at least one student.',
            'student_ids.*.required' => 'Each selected student must be valid.',
            'student_ids.*.integer' => 'Each selected student must be a valid ID.',
            'student_ids.*.distinct' => 'Selected students must be unique.',
            'student_ids.*.exists' => 'One or more selected students do not exist.',
            'is_cleared.required' => 'Please choose a clearance state.',
            'is_cleared.boolean' => 'Clearance state must be valid.',
            'remarks.string' => 'Remarks must be a valid string.',
        ];
    }
}
