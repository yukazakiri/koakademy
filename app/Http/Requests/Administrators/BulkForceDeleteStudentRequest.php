<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Support\AdministratorPermissionChecker;
use Illuminate\Foundation\Http\FormRequest;

final class BulkForceDeleteStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Intentionally permits the portal Admin role alongside explicit ForceDelete grants
        // (matching AdministratorStudentManagementController::forceDestroy), while restricting
        // Registrar and other staff roles unless they hold ForceDelete:Student or ForceDeleteAny:Student.
        return AdministratorPermissionChecker::userHasAnyPermission(
            $this->user(),
            ['ForceDelete:Student', 'ForceDeleteAny:Student'],
            [UserRole::Admin],
        );
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
            'confirm_text' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'Select at least one student.',
            'student_ids.array' => 'Selected students must be a valid list.',
            'student_ids.min' => 'Select at least one student.',
            'student_ids.*.required' => 'Each selected student must be valid.',
            'student_ids.*.integer' => 'Each selected student must be a valid ID.',
            'student_ids.*.distinct' => 'Selected students must be unique.',
            'student_ids.*.exists' => 'One or more selected students do not exist.',
            'confirm_text.required' => 'Type the confirmation text to proceed.',
        ];
    }
}
