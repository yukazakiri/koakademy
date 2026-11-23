<?php

declare(strict_types=1);

namespace App\Filament\Resources\Classes\Api\Requests;

use App\Support\ScheduleDelivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateClassesRequest extends FormRequest
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
            'subject_code' => 'required',
            'faculty_id' => 'required',
            'academic_year' => 'required',
            'semester' => 'required',
            'schedule_id' => 'required',
            'school_year' => 'required',
            'course_codes' => 'required',
            'section' => 'required',
            'delivery_mode' => ['sometimes', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'room_id' => ScheduleDelivery::isOnline($this->input('delivery_mode')) ? 'nullable' : 'required',
            'classification' => 'required',
            'maximum_slots' => 'required',
            'shs_track_id' => 'required',
            'shs_strand_id' => 'required',
            'grade_level' => 'required',
            'subject_id' => 'required',
            'subject_ids' => 'required',

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
            'settings.notify_students_on_schedule_changes' => 'nullable|boolean',
            'settings.custom' => 'nullable|array',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('delivery_mode')) {
            $this->merge(['delivery_mode' => ScheduleDelivery::normalizeInput($this->input('delivery_mode'))]);
        }

        if (ScheduleDelivery::isOnline($this->input('delivery_mode'))) {
            $this->merge(['room_id' => null]);
        }
    }
}
