<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\User;
use App\Rules\NonOverlappingScheduleBlocksRule;
use App\Rules\ScheduleOverlapRule;
use App\Support\AdministratorPermissionChecker;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdministratorPermissionChecker::userHasAnyPermission(
            $this->user() instanceof User ? $this->user() : null,
            'Update:Classes',
            [UserRole::Admin],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $classRouteParameter = $this->route('class');
        $classIdForScheduleExclusion = $classRouteParameter instanceof Classes
            ? $classRouteParameter->id
            : (is_numeric($classRouteParameter) ? (int) $classRouteParameter : null);

        return [
            'classification' => ['sometimes', Rule::in(['college', 'shs'])],

            // College fields
            'course_codes' => ['exclude_unless:classification,college', 'sometimes', 'nullable', 'array'],
            'course_codes.*' => ['integer'],
            'subject_ids' => ['exclude_unless:classification,college', 'sometimes', 'nullable', 'array'],
            'subject_ids.*' => ['integer'],
            'subject_code' => ['exclude_unless:classification,college', 'sometimes', 'nullable', 'string', 'max:255'],
            'subject_id' => ['exclude_unless:classification,college', 'sometimes', 'nullable', 'integer'],
            'academic_year' => ['exclude_unless:classification,college', 'sometimes', 'nullable', 'integer', 'min:1', 'max:4'],

            // SHS fields
            'shs_track_id' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', 'integer'],
            'shs_strand_id' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', 'integer'],
            'grade_level' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', Rule::in(['Grade 11', 'Grade 12'])],
            'subject_code_shs' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', 'string', 'max:255'],

            // Common fields
            'faculty_id' => ['sometimes', 'nullable', 'string', 'exists:faculty,id'],
            'semester' => ['sometimes', 'nullable', Rule::in(['1', '2', 1, 2, 'summer'])],
            'school_year' => ['sometimes', 'nullable', 'string', 'max:50'],
            'section' => ['sometimes', 'nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'delivery_mode' => ['sometimes', Rule::in(['face_to_face', 'online'])],
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'maximum_slots' => ['sometimes', 'nullable', 'integer', 'min:1'],

            // Schedule
            'schedules' => ['sometimes', 'array', new NonOverlappingScheduleBlocksRule, new ScheduleOverlapRule($classIdForScheduleExclusion)],
            'schedules.*.id' => [
                'sometimes',
                'integer',
                'distinct',
                Rule::exists('schedule', 'id')->where('class_id', $classIdForScheduleExclusion),
            ],
            'schedules.*.day_of_week' => ['required_with:schedules', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'schedules.*.start_time' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.end_time' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'schedules.*.component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab'])],
            'schedules.*.pattern_group_id' => ['nullable', 'string', 'max:64'],
            'schedules.*.status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'schedules.*.subject_id' => ['nullable', 'integer', 'exists:subject,id'],

            // Settings
            'settings' => ['sometimes', 'nullable', 'array'],
            'settings.background_color' => ['nullable', 'string', 'max:50'],
            'settings.accent_color' => ['nullable', 'string', 'max:50'],
            'settings.banner_image' => ['nullable'],
            'settings.theme' => ['nullable', Rule::in(['default', 'modern', 'classic', 'minimal', 'vibrant'])],
            'settings.enable_announcements' => ['nullable', 'boolean'],
            'settings.enable_grade_visibility' => ['nullable', 'boolean'],
            'settings.enable_attendance_tracking' => ['nullable', 'boolean'],
            'settings.allow_late_submissions' => ['nullable', 'boolean'],
            'settings.enable_discussion_board' => ['nullable', 'boolean'],
            'settings.notify_students_on_schedule_changes' => ['nullable', 'boolean'],
            'settings.custom' => ['nullable', 'array'],
            'remove_banner_image' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->exists('schedules')) {
                return;
            }

            if ($this->only([
                'delivery_mode', 'faculty_id', 'school_year', 'semester', 'classification',
                'section', 'academic_year', 'course_codes', 'shs_strand_id', 'grade_level',
            ]) === []) {
                return;
            }

            $class = $this->route('class');
            if (! $class instanceof Classes) {
                return;
            }

            // A faculty, cohort, or term change must also validate retained blocks.
            $schedules = $class->schedules()->get()->map(fn ($schedule): array => [
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i:s'),
                'end_time' => $schedule->end_time?->format('H:i:s'),
                'room_id' => $schedule->room_id,
            ])->all();

            $overlapValidator = ValidatorFacade::make(
                [...$this->all(), 'schedules' => $schedules],
                ['schedules' => [new ScheduleOverlapRule($class->id)]],
            );

            if ($overlapValidator->fails()) {
                $validator->errors()->merge($overlapValidator->errors());
            }
        }];
    }

    /**
     * An online class never occupies a room, so rooms are stripped before
     * validation. This keeps room-conflict checks from firing on rooms that
     * were left over from a face to face draft.
     */
    protected function prepareForValidation(): void
    {
        $class = $this->route('class');
        if (! $class instanceof Classes && is_numeric($class)) {
            $class = Classes::query()->find((int) $class);
        }

        if ($this->input('delivery_mode', $class instanceof Classes ? $class->delivery_mode : null) !== Classes::DELIVERY_ONLINE) {
            return;
        }

        $schedules = $this->input('schedules');
        $this->merge(['room_id' => null]);

        // Omitted schedules must stay omitted on a partial update.
        if (is_array($schedules)) {
            $this->merge(['schedules' => array_map(
                static fn (mixed $schedule): mixed => is_array($schedule) ? [...$schedule, 'room_id' => null] : $schedule,
                $schedules,
            )]);
        }
    }
}
