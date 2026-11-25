<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Schedule;
use App\Support\ScheduleDelivery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $schedule = $this->route('schedule');
        $allowedDays = ScheduleDelivery::allowedDays();
        if ($schedule instanceof Schedule && $schedule->day_of_week === 'Sunday' && ! in_array('Sunday', $allowedDays, true)) {
            $allowedDays[] = 'Sunday';
        }

        return [
            'day_of_week' => ['required', 'string', Rule::in($allowedDays)],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'delivery_mode' => ['sometimes', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
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
        $allowsSunday = ScheduleDelivery::allowsSunday();

        return [
            'end_time.after' => 'The end time must be after the start time.',
            'day_of_week.in' => $allowsSunday
                ? 'The day must be a valid day of the week (Monday through Sunday).'
                : 'Sunday scheduling is disabled. Enable Sunday scheduling in Scheduling Rules to schedule on Sunday.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $schedule = $this->route('schedule');
        if ($schedule instanceof Schedule) {
            $this->merge(ScheduleDelivery::normalizeBlock($this->all(), $schedule->delivery_mode));
        }
    }
}
