<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Classes;
use App\Rules\NonOverlappingScheduleBlocksRule;
use App\Rules\ScheduleOverlapRule;
use App\Support\ScheduleDelivery;
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
            'schedules.*.delivery_mode' => ['sometimes', Rule::in(['face_to_face', 'online'])],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $class = $this->route('class');
        if ($class instanceof Classes) {
            $this->merge(['schedules' => ScheduleDelivery::normalizeSchedules($this->input('schedules'), $class)]);
        }
    }
}
