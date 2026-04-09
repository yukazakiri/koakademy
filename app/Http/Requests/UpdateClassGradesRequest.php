<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateClassGradesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'grades' => ['required', 'array', 'min:1'],
            'grades.*.enrollment_id' => ['required', 'integer', 'exists:class_enrollments,id'],
            'grades.*.prelim' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.midterm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.final' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.average' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
