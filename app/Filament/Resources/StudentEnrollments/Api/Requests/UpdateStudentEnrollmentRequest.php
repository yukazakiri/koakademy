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
            'student_id' => 'required',
            'course_id' => 'required',
            'status' => 'required',
            'semester' => 'required',
            'academic_year' => 'required',
            'school_year' => 'required',
            'deleted_at' => 'required',
            'downpayment' => 'required',
            'remarks' => 'required|string',
            'payment_method' => 'required',
        ];
    }
}
