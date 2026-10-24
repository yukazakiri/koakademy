<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Schedule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateScheduleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdministrative() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'string', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'component_type' => ['nullable', 'string', 'in:lecture,lab'],
            'status' => ['nullable', 'string', 'in:draft,published'],
            'apply_to_pattern_group' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_time.after' => 'The end time must be after the start time.',
            'day_of_week.in' => 'The day must be a valid weekday (Monday through Saturday).',
        ];
    }

    protected function prepareForValidation(): void
    {
        $schedule = $this->route('schedule');
        if ($schedule instanceof Schedule && $schedule->class?->isOnline()) {
            $this->merge(['room_id' => null]);
        }
    }
}
