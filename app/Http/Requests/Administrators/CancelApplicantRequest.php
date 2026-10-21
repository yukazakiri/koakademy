<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\ApplicantCancellationReason;
use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CancelApplicantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ApplicantCancellationReason::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'send_email' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Select the reason this application is being closed.',
            'reason.enum' => 'Select a valid reason.',
            'note.max' => 'The note may not be longer than 2000 characters.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $reason = ApplicantCancellationReason::tryFrom((string) $this->input('reason'));

            if ($reason?->requiresNote() && blank($this->input('note'))) {
                $validator->errors()->add('note', 'Please explain the decision when cancelling for this reason.');
            }

            $student = $this->route('student');

            if ($student instanceof Student && $student->status !== StudentStatus::Applicant) {
                $validator->errors()->add('reason', 'This application is already closed and cannot be cancelled again.');
            }
        });
    }
}
