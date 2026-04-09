<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateAttendanceSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'isFaculty') && $user->isFaculty();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i', 'after:starts_at'],
            'lock_session' => ['sometimes', 'boolean'],
        ];
    }
}
