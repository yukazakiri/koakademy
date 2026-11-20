<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Filament\Resources\Rooms\Schemas\RoomForm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'class_code' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50', Rule::in(array_keys(RoomForm::roomTypeOptions()))],
            'capacity' => ['required', 'integer', 'min:1', 'max:5000'],
            'building' => ['nullable', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Room name is required.',
            'type.required' => 'Please select a room type.',
            'type.in' => 'Please select a valid room type.',
            'capacity.required' => 'Room capacity is required.',
            'capacity.min' => 'Capacity must be at least 1 seat.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $schoolId = $this->input('school_id');

        $this->merge([
            'school_id' => ($schoolId === '' || $schoolId === 'none' || $schoolId === null) ? null : (int) $schoolId,
            'type' => $this->input('type') ?: 'lecture',
            'capacity' => $this->input('capacity') !== null && $this->input('capacity') !== '' ? (int) $this->input('capacity') : 40,
            'is_active' => $this->has('is_active') ? filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN) : true,
        ]);
    }
}
