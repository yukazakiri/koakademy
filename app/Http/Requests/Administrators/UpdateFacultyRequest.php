<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFacultyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Faculty|null $faculty */
        $faculty = $this->route('faculty');

        return [
            'faculty_id_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('faculty', 'faculty_id_number')->ignore($faculty?->id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('faculty', 'email')->ignore($faculty?->id),
            ],
            'department' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive', 'on_leave'])],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'birth_date' => ['nullable', 'date'],
            'age' => ['nullable', 'integer', 'min:16', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'office_hours' => ['nullable', 'string', 'max:1000'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'biography' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'courses_taught' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'photo_url' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
