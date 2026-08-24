<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

final class StoreRegistrarStudentProfileImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', StudentEnrollment::class)
            && Gate::allows('exportDetailed', StudentEnrollment::class)
            && Gate::allows('update', StudentEnrollment::class)
            && Gate::allows('viewAny', Student::class)
            && Gate::allows('update', Student::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                File::types(['xlsx'])->max('10mb'),
            ],
        ];
    }
}
