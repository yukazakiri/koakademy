<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\User;
use App\Rules\NonOverlappingScheduleBlocksRule;
use App\Rules\ScheduleOverlapRule;
use App\Support\AdministratorPermissionChecker;
use App\Support\ScheduleDelivery;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateClassScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdministratorPermissionChecker::userHasAnyPermission(
            $this->user() instanceof User ? $this->user() : null,
            'Update:Classes',
            [UserRole::Admin],
        );
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $class = $this->route('class');

        return [
            'schedules' => ['present', 'array', new NonOverlappingScheduleBlocksRule],
            'schedules.*' => ['required', 'array'],
            'schedules.*.id' => [
                'sometimes', 'integer', 'distinct',
                Rule::exists('schedule', 'id')->where('class_id', $class instanceof Classes ? $class->id : null)->whereNull('deleted_at'),
            ],
            'schedules.*.day_of_week' => ['required', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.delivery_mode' => ['required', Rule::in(['face_to_face', 'online'])],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ];
    }

    /** @return array<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $class = $this->route('class');
            if ($validator->errors()->isNotEmpty() || ! $class instanceof Classes) {
                return;
            }

            // Check each meeting with the same authoritative rule used by class updates,
            // attaching the conflict to the meeting rather than the whole form.
            foreach ($this->input('schedules', []) as $index => $schedule) {
                $conflicts = ValidatorFacade::make(
                    ['schedules' => [$schedule]],
                    ['schedules' => [new ScheduleOverlapRule($class->id)]],
                );

                if ($conflicts->fails()) {
                    $validator->errors()->add("schedules.{$index}", $conflicts->errors()->first('schedules'));
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $schedules = $this->input('schedules');
        if (! is_array($schedules)) {
            return;
        }

        $this->merge(['schedules' => array_map(
            fn (mixed $schedule): mixed => is_array($schedule) ? ScheduleDelivery::normalizeBlock($schedule) : $schedule,
            $schedules,
        )]);
    }
}
