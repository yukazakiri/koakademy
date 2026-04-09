<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class BulkEmailStudentsRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
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
            'subject.required' => 'Please provide a subject.',
            'subject.max' => 'Subject may not be greater than 150 characters.',
            'message.required' => 'Please provide a message.',
            'message.max' => 'Message may not be greater than 5000 characters.',
        ];
    }
}
