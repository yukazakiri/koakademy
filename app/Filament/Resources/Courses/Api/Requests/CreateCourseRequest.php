<?php

declare(strict_types=1);

namespace App\Filament\Resources\Courses\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCourseRequest extends FormRequest
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
            'code' => 'required',
            'title' => 'required',
            'description' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'lec_per_unit' => 'required',
            'lab_per_unit' => 'required',
            'remarks' => 'required|string',
            'curriculum_year' => 'required',
            'miscelaneous' => 'required',
            'units' => 'required',
            'year_level' => 'required',
            'semester' => 'required',
            'school_year' => 'required',
            'miscellaneous' => 'required',
            'is_active' => 'required',
        ];
    }
}
