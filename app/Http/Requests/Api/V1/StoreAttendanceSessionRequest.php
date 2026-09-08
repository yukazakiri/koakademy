<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFaculty() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'schedule_id' => ['nullable', 'integer'],
            'session_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after_or_equal:starts_at'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_locked' => ['sometimes', 'boolean'],
            'is_no_meeting' => ['sometimes', 'boolean'],
            'no_meeting_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
