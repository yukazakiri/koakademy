<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ConfirmRegistrarStudentProfileImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', StudentEnrollment::class)
            && Gate::allows('update', Student::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'student_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
