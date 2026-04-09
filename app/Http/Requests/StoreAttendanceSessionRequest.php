<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'isFaculty') && $user->isFaculty();
    }

    /**
     * @return array<string, list<string|Rule>>
     */
    public function rules(): array
    {
        return [
            'session_date' => ['required', 'date'],
            'schedule_id' => ['required', 'integer', 'exists:schedule,id'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'default_status' => ['nullable', 'string', Rule::in(AttendanceStatus::values())],
            'mark_all' => ['sometimes', 'boolean'],
            'is_no_meeting' => ['sometimes', 'boolean'],
            'no_meeting_reason' => ['nullable', 'string', 'max:120'],
        ];
    }
}
