<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Classes;
use App\Rules\ScheduleOverlapRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateClassRequest extends FormRequest
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
            'room_id' => ['sometimes', 'nullable', 'integer', 'exists:rooms,id'],
            'maximum_slots' => ['sometimes', 'nullable', 'integer', 'min:1'],

            // Schedule
            'schedules' => ['sometimes', 'array', new ScheduleOverlapRule($classIdForScheduleExclusion)],
            'schedules.*.day_of_week' => ['required_with:schedules', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'schedules.*.start_time' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.end_time' => ['required_with:schedules', 'date_format:H:i'],
            'schedules.*.room_id' => ['required_with:schedules', 'integer', 'exists:rooms,id'],

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
            'settings.custom' => ['nullable', 'array'],
            'remove_banner_image' => ['sometimes', 'boolean'],
        ];
    }
}
