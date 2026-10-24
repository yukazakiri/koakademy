<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Classes;
use App\Rules\NonOverlappingScheduleBlocksRule;
use App\Rules\ScheduleOverlapRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateClassSchedulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'isFaculty') && $user->isFaculty();
    }

    /**
     * @return array<string, list<string|Rule|ScheduleOverlapRule>>
     */
    public function rules(): array
    {
        $classRouteParameter = $this->route('class');
        $classIdForScheduleExclusion = $classRouteParameter instanceof Classes
            ? $classRouteParameter->id
            : (is_numeric($classRouteParameter) ? (int) $classRouteParameter : null);

        return [
            'schedules' => ['required', 'array', new NonOverlappingScheduleBlocksRule, new ScheduleOverlapRule($classIdForScheduleExclusion)],
            'schedules.*.id' => ['sometimes', 'integer', 'distinct', Rule::exists('schedule', 'id')->where('class_id', $classIdForScheduleExclusion)],
            'schedules.*.day_of_week' => [
                'required',
                'string',
                Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']),
            ],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $class = $this->route('class');
        $schedules = $this->input('schedules');

        if ($class instanceof Classes && $class->isOnline() && is_array($schedules)) {
            $this->merge(['schedules' => array_map(
                static fn (mixed $schedule): mixed => is_array($schedule) ? [...$schedule, 'room_id' => null] : $schedule,
                $schedules,
            )]);
        }
    }
}
