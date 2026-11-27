<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\NonOverlappingScheduleBlocksRule;
use App\Rules\ScheduleOverlapRule;
use App\Support\AdministratorPermissionChecker;
use App\Support\ScheduleDelivery;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdministratorPermissionChecker::userHasAnyPermission(
            $this->user() instanceof User ? $this->user() : null,
            'Create:Classes',
            [UserRole::Admin],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'classification' => ['required', Rule::in(['college', 'shs'])],

            // College fields
            'course_codes' => ['exclude_unless:classification,college', 'required_if:classification,college', 'array'],
            'course_codes.*' => ['integer'],
            'subject_ids' => ['exclude_unless:classification,college', 'required_if:classification,college', 'array'],
            'subject_ids.*' => ['integer'],
            'subject_code' => ['exclude_unless:classification,college', 'nullable', 'string', 'max:255'],
            'subject_id' => ['exclude_unless:classification,college', 'nullable', 'integer'],
            'academic_year' => ['exclude_unless:classification,college', 'required_if:classification,college', 'integer', 'min:1', 'max:4'],

            // SHS fields
            'shs_track_id' => ['exclude_unless:classification,shs', 'required_if:classification,shs', 'integer', 'exists:shs_tracks,id'],
            'shs_strand_id' => ['exclude_unless:classification,shs', 'required_if:classification,shs', 'integer', 'exists:shs_strands,id'],
            'grade_level' => ['exclude_unless:classification,shs', 'required_if:classification,shs', Rule::in(['Grade 11', 'Grade 12'])],
            'subject_code_shs' => ['exclude_unless:classification,shs', 'required_if:classification,shs', 'string', 'max:255'],

            // Common fields
            'faculty_id' => ['nullable', 'string', 'exists:faculty,id'],
            'semester' => ['required', Rule::in(['1', '2', 1, 2, 'summer'])],
            'school_year' => ['required', 'string', 'max:50'],
            'start_date' => ['nullable', 'date'],
            'section' => ['required', 'string', 'max:20'],
            'delivery_mode' => ['sometimes', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'maximum_slots' => ['required', 'integer', 'min:1'],
            'schedule_status' => ['sometimes', 'nullable', Rule::in(['draft', 'published'])],
            'notify_enrolled' => ['sometimes', 'boolean'],
            'redirect_to' => ['sometimes', 'nullable', Rule::in(['index', 'show', 'duplicate'])],

            // Schedule (optional so classes can be created as unscheduled offerings first)
            'schedules' => ['nullable', 'array', new NonOverlappingScheduleBlocksRule, new ScheduleOverlapRule],
            'schedules.*.id' => ['prohibited'],
            'schedules.*.day_of_week' => ['required', Rule::in(ScheduleDelivery::allowedDays())],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.delivery_mode' => ['sometimes', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'schedules.*.component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab', 'laboratory'])],
            'schedules.*.pattern_group_id' => ['nullable', 'string', 'max:64'],
            'schedules.*.status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'schedules.*.subject_id' => ['nullable', 'integer', 'exists:subject,id'],

            // Settings
            'settings' => ['nullable', 'array'],
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classification.required' => 'Please select a class type.',
            'course_codes.required_if' => 'Please select at least one course.',
            'subject_ids.required_if' => 'Please select at least one subject.',
            'subject_code_shs.required_if' => 'Please select an SHS subject.',
            'schedules.required' => 'Please add at least one schedule entry.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('delivery_mode')) {
            $normalizedDeliveryMode = ScheduleDelivery::normalizeInput($this->input('delivery_mode'));
            $this->merge(['delivery_mode' => $normalizedDeliveryMode]);
        }

        if (ScheduleDelivery::isOnline($this->input('delivery_mode'))) {
            $this->merge(['room_id' => null]);
        }

        if ($this->exists('schedules')) {
            $this->merge(['schedules' => ScheduleDelivery::normalizeSchedules(
                $this->input('schedules'),
                default: is_string($this->input('delivery_mode')) ? $this->input('delivery_mode') : null,
            )]);
        }
    }
}
