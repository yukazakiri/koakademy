<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Rules\ScheduleOverlapRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'maximum_slots' => ['required', 'integer', 'min:1'],

            // Schedule
            'schedules' => ['required', 'array', new ScheduleOverlapRule],
            'schedules.*.day_of_week' => ['required', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'schedules.*.room_id' => ['required', 'integer', 'exists:rooms,id'],

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
}
