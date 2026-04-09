<?php

declare(strict_types=1);

namespace App\Filament\Resources\Faculties\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateFacultyRequest extends FormRequest
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
            'first_name' => 'required',
            'last_name' => 'required',
            'middle_name' => 'required',
            'email' => 'required',
            'phone_number' => 'required',
            'department' => 'required',
            'office_hours' => 'required',
            'birth_date' => 'required|date',
            'address_line1' => 'required',
            'biography' => 'required|string',
            'education' => 'required|string',
            'courses_taught' => 'required|string',
            'photo_url' => 'required',
            'status' => 'required',
            'gender' => 'required',
            'age' => 'required',
            'password' => 'required',
            'remember_token' => 'required',
            'email_verified_at' => 'required',
            'faculty_code' => 'required',
            'faculty_id_number' => 'required',
            'full_name' => 'required',
        ];
    }
}
