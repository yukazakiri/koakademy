<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Support\ScheduleDelivery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BatchUpdateSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdministrative() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowedDays = ScheduleDelivery::allowedDays();

        return [
            'operations' => ['required', 'array', 'min:1', 'max:50'],
            'operations.*.schedule_id' => ['required', 'integer', 'exists:schedule,id'],
            'operations.*.day_of_week' => ['required', 'string', Rule::in($allowedDays)],
            'operations.*.start_time' => ['required', 'date_format:H:i'],
            'operations.*.end_time' => ['required', 'date_format:H:i', 'after:operations.*.start_time'],
            'operations.*.delivery_mode' => ['sometimes', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'operations.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'operations.*.component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab'])],
            'operations.*.status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operations.*.end_time.after' => 'Each schedule block end time must be after its start time.',
            'operations.*.day_of_week.in' => ScheduleDelivery::allowsSunday()
                ? 'Each schedule block must use a valid day of the week.'
                : 'Sunday scheduling is disabled. Enable Sunday scheduling in Scheduling Rules to schedule on Sunday.',
        ];
    }
}
