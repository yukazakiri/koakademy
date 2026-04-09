<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateFacultyRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:100'],
            'office_hours' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'address_line1' => ['nullable', 'string', 'max:500'],
            'biography' => ['nullable', 'string'],
            'education' => ['nullable', 'string'],
            'courses_taught' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'age' => ['nullable', 'integer', 'min:0'],
            'password' => ['nullable', 'string', 'min:8'],
            'faculty_id_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
