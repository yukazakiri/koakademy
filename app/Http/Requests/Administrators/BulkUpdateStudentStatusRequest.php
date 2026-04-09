<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\StudentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class BulkUpdateStudentStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(StudentStatus::class)],
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
            'status.required' => 'Please choose a status.',
        ];
    }
}
