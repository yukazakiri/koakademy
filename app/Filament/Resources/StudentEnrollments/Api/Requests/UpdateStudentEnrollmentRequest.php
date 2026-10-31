<?php

declare(strict_types=1);

namespace App\Filament\Resources\StudentEnrollments\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateStudentEnrollmentRequest extends FormRequest
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
            'course_id' => ['sometimes', 'integer', 'exists:courses,id'],
            'status' => ['sometimes', 'string'],
            'semester' => ['sometimes', 'integer', 'in:1,2'],
            'academic_year' => ['sometimes', 'integer', 'min:1', 'max:4'],
            'school_year' => ['sometimes', 'string'],
            'downpayment' => ['sometimes', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
