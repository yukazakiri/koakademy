<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAttendanceRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && method_exists($user, 'isFaculty') && $user->isFaculty();
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*.class_enrollment_id' => ['required', 'integer', 'exists:class_enrollments,id'],
            'records.*.status' => ['required', 'string', Rule::in(AttendanceStatus::values())],
            'records.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }
}
