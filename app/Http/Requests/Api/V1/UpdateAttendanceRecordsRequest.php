<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class UpdateAttendanceRecordsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isFaculty() === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'min:1'],
            'records.*.class_enrollment_id' => ['required', 'integer'],
            'records.*.status' => ['required', new Enum(AttendanceStatus::class)],
            'records.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
