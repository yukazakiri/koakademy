<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\ApplicantCancellationReason;
use App\Enums\ScholarshipType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ApplicantBulkActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['notify_approval', 'set_scholarship', 'convert_to_enrollment', 'cancel'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['required', 'integer', 'exists:students,id'],
            'scholarship_type' => ['nullable', 'string', Rule::enum(ScholarshipType::class)],
            'reason' => ['required_if:action,cancel', 'nullable', 'string', Rule::enum(ApplicantCancellationReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'send_email' => ['nullable', 'boolean'],
        ];
    }
}
