<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Requests;

use App\Rules\ScheduleOverlapRule;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateClassesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_code' => 'sometimes|nullable|string',
            'faculty_id' => 'sometimes|nullable|string',
            'academic_year' => 'sometimes|nullable|string',
            'semester' => 'sometimes|nullable|string',
            'schedule_id' => 'sometimes|nullable|integer',
            'school_year' => 'sometimes|nullable|string',
            'course_codes' => 'sometimes|nullable|array',
            'course_codes.*' => 'nullable|integer',
            'section' => 'sometimes|nullable|string',
            'room_id' => 'sometimes|nullable|integer',
            'classification' => 'sometimes|nullable|string',
            'maximum_slots' => 'sometimes|nullable|integer',
            'shs_track_id' => 'sometimes|nullable|integer',
            'shs_strand_id' => 'sometimes|nullable|integer',
            'grade_level' => 'sometimes|nullable|string',
            'subject_id' => 'sometimes|nullable|integer',
            'subject_ids' => 'sometimes|nullable|array',
            'subject_ids.*' => 'nullable|integer',

            // Schedule Validation
            'schedules' => ['sometimes', 'array', new ScheduleOverlapRule],
            'schedules.*.day_of_week' => 'required_with:schedules|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'schedules.*.start_time' => 'required_with:schedules',
            'schedules.*.end_time' => 'required_with:schedules|after:schedules.*.start_time',
            'schedules.*.room_id' => 'required_with:schedules|exists:rooms,id',

            // Settings validation
            'settings' => 'nullable|array',
            'settings.background_color' => 'nullable|string|max:50',
            'settings.accent_color' => 'nullable|string|max:50',
            'settings.banner_image' => 'nullable|string|max:500',
            'settings.theme' => 'nullable|string|in:default,modern,classic,minimal,vibrant',
            'settings.enable_announcements' => 'nullable|boolean',
            'settings.enable_grade_visibility' => 'nullable|boolean',
            'settings.enable_attendance_tracking' => 'nullable|boolean',
            'settings.allow_late_submissions' => 'nullable|boolean',
            'settings.enable_discussion_board' => 'nullable|boolean',
            'settings.custom' => 'nullable|array',
        ];
    }
}
