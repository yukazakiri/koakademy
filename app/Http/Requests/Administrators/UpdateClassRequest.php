<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
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
            'shs_track_id' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', 'integer', 'exists:shs_tracks,id'],
            'shs_strand_id' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', 'integer', 'exists:shs_strands,id'],
            'grade_level' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', Rule::in(['Grade 11', 'Grade 12'])],
            'subject_code_shs' => ['exclude_unless:classification,shs', 'sometimes', 'nullable', 'string', 'max:255'],

            // Common fields
            'faculty_id' => ['sometimes', 'nullable', 'string', 'exists:faculty,id'],
            'semester' => ['sometimes', 'nullable', Rule::in(['1', '2', 1, 2, 'summer'])],
            'school_year' => ['sometimes', 'nullable', 'string', 'max:50'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'section' => ['sometimes', 'nullable', 'string', 'max:20'],
            'delivery_mode' => ['sometimes', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'apply_delivery_mode_to_schedules' => ['sometimes', 'boolean'],
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'maximum_slots' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'allow_below_enrolled' => ['sometimes', 'boolean'],
            'schedule_status' => ['sometimes', 'nullable', Rule::in(['draft', 'published'])],
            'notify_enrolled' => ['sometimes', 'boolean'],
            'redirect_to' => ['sometimes', 'nullable', Rule::in(['index', 'show', 'duplicate'])],

            // Schedule
            'schedules_match_by_id' => ['sometimes', 'boolean'],
            'schedules' => ['sometimes', 'array', new NonOverlappingScheduleBlocksRule, new ScheduleOverlapRule($classIdForScheduleExclusion)],
            'schedules.*.id' => [
                'sometimes',
                'integer',
                'distinct',
                Rule::exists('schedule', 'id')->where('class_id', $classIdForScheduleExclusion)->whereNull('deleted_at'),
            ],
            'schedules.*.day_of_week' => ['required_with:schedules', Rule::in(ScheduleDelivery::allowedDays())],
            'schedules.*.start_time' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.end_time' => ['required_with:schedules', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.delivery_mode' => ['sometimes', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'schedules.*.component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab', 'laboratory'])],
            'schedules.*.pattern_group_id' => ['nullable', 'string', 'max:64'],
            'schedules.*.status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'schedules.*.subject_id' => ['nullable', 'integer', 'exists:subject,id'],

            // Settings
            'settings' => ['sometimes', 'nullable', 'array'],
            'settings.background_color' => ['nullable', 'string', 'max:50'],
            'settings.accent_color' => ['nullable', 'string', 'max:50'],
            'settings.banner_image' => [
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! $value instanceof \Illuminate\Http\UploadedFile) {
                        $fail('The banner image must be a valid uploaded image file.');

                        return;
                    }
                    $ext = mb_strtolower((string) $value->getClientOriginalExtension());
                    if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'], true)) {
                        $fail('The banner image must be a JPG, PNG, WebP, or SVG file.');
                    }
                    if ($value->getSize() !== false && $value->getSize() > 4 * 1024 * 1024) {
                        $fail('The banner image may not be larger than 4 MB.');
                    }
                },
            ],
            'settings.theme' => ['nullable', Rule::in(['default', 'modern', 'classic', 'minimal', 'vibrant'])],
            'settings.enable_announcements' => ['nullable', 'boolean'],
            'settings.enable_grade_visibility' => ['nullable', 'boolean'],
            'settings.enable_attendance_tracking' => ['nullable', 'boolean'],
            'settings.enable_online_resources' => ['nullable', 'boolean'],
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
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->exists('maximum_slots') || $this->boolean('allow_below_enrolled')) {
                    return;
                }

                $class = $this->route('class');
                if (! $class instanceof Classes) {
                    return;
                }

                $requestedSlots = (int) $this->input('maximum_slots');
                if ($requestedSlots <= 0) {
                    return;
                }

                $activeEnrolled = $class->class_enrollments()
                    ->where(fn ($query) => $query->whereNull('status')->orWhere('status', true))
                    ->where(fn ($query) => $query->whereNull('enrollment_status')->orWhereNotIn('enrollment_status', [
                        ClassEnrollment::STATUS_DROPPED,
                        ClassEnrollment::STATUS_WITHDRAWN,
                    ]))
                    ->count();

                if ($activeEnrolled > 0 && $requestedSlots < $activeEnrolled && $requestedSlots < (int) ($class->maximum_slots ?? 0)) {
                    $validator->errors()->add(
                        'maximum_slots',
                        sprintf(
                            'Capacity cannot be reduced to %d while %d active students are currently enrolled.',
                            $requestedSlots,
                            $activeEnrolled,
                        ),
                    );
                }
            },
            function (Validator $validator): void {
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
                $applyMode = $this->boolean('apply_delivery_mode_to_schedules') && is_string($this->input('delivery_mode'));
                $targetDeliveryMode = $applyMode ? ScheduleDelivery::canonicalize($this->input('delivery_mode')) : null;
                $schedules = $class->schedules()->get()->map(function ($schedule) use ($applyMode, $targetDeliveryMode): array {
                    $effectiveMode = $applyMode && $targetDeliveryMode !== null ? $targetDeliveryMode : $schedule->delivery_mode;

                    return [
                        'day_of_week' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time?->format('H:i:s'),
                        'end_time' => $schedule->end_time?->format('H:i:s'),
                        'room_id' => ScheduleDelivery::isOnline($effectiveMode) ? null : $schedule->room_id,
                        'delivery_mode' => $effectiveMode,
                    ];
                })->all();

                $overlapValidator = ValidatorFacade::make(
                    [...$this->all(), 'schedules' => $schedules],
                    ['schedules' => [new ScheduleOverlapRule($class->id)]],
                );

                if ($overlapValidator->fails()) {
                    $validator->errors()->merge($overlapValidator->errors());
                }
            }];
    }

    protected function prepareForValidation(): void
    {
        $class = $this->route('class');
        if (! $class instanceof Classes && is_numeric($class)) {
            $class = Classes::query()->find((int) $class);
        }

        if ($this->exists('delivery_mode')) {
            $this->merge(['delivery_mode' => ScheduleDelivery::normalizeInput($this->input('delivery_mode'))]);
        }

        if (ScheduleDelivery::isOnline($this->input('delivery_mode', $class?->delivery_mode))) {
            $this->merge(['room_id' => null]);
        }

        if ($this->exists('schedules')) {
            $this->merge(['schedules' => ScheduleDelivery::normalizeSchedules(
                $this->input('schedules'),
                $class instanceof Classes ? $class : null,
                is_string($this->input('delivery_mode')) ? $this->input('delivery_mode') : null,
                ! $this->boolean('schedules_match_by_id'),
            )]);
        }
    }
}
