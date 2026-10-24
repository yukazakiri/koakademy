<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\NonOverlappingScheduleBlocksRule;
use App\Rules\ScheduleOverlapRule;
use App\Support\AdministratorPermissionChecker;
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
            'shs_track_id' => ['exclude_unless:classification,shs', 'required_if:classification,shs', 'integer'],
            'shs_strand_id' => ['exclude_unless:classification,shs', 'required_if:classification,shs', 'integer'],
            'grade_level' => ['exclude_unless:classification,shs', 'required_if:classification,shs', Rule::in(['Grade 11', 'Grade 12'])],
            'subject_code_shs' => ['exclude_unless:classification,shs', 'required_if:classification,shs', 'string', 'max:255'],

            // Common fields
            'faculty_id' => ['nullable', 'string', 'exists:faculty,id'],
            'semester' => ['required', Rule::in(['1', '2', 1, 2, 'summer'])],
            'school_year' => ['required', 'string', 'max:50'],
            'section' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'delivery_mode' => ['sometimes', Rule::in(['face_to_face', 'online'])],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'maximum_slots' => ['required', 'integer', 'min:1'],

            // Schedule (optional so classes can be created as unscheduled offerings first)
            'schedules' => ['nullable', 'array', new NonOverlappingScheduleBlocksRule, new ScheduleOverlapRule],
            'schedules.*.id' => ['prohibited'],
            'schedules.*.day_of_week' => ['required', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'schedules.*.component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab'])],
            'schedules.*.pattern_group_id' => ['nullable', 'string', 'max:64'],
            'schedules.*.status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'schedules.*.subject_id' => ['nullable', 'integer', 'exists:subject,id'],

            // Settings
            'settings' => ['nullable', 'array'],
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

    /**
     * An online class never occupies a room, so rooms are stripped before
     * validation. This keeps room-conflict checks from firing on rooms that
     * were left over from a face to face draft.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('delivery_mode') !== 'online') {
            return;
        }

        $schedules = $this->input('schedules');

        $this->merge([
            'room_id' => null,
            'schedules' => is_array($schedules)
                ? array_map(
                    static fn (mixed $schedule): mixed => is_array($schedule) ? [...$schedule, 'room_id' => null] : $schedule,
                    $schedules,
                )
                : $schedules,
        ]);
    }
}
