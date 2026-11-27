<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Support\AdministratorPermissionChecker;
use Illuminate\Foundation\Http\FormRequest;

final class BulkDeleteStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdministratorPermissionChecker::userHasAnyPermission(
            $this->user(),
            ['Delete:Student', 'DeleteAny:Student'],
            [UserRole::Admin, UserRole::Registrar],
        );
    }

    public function rules(): array
    {
        return [
            'student_ids' => ['required', 'array', 'min:1', 'max:500'],
            'student_ids.*' => ['required', 'integer', 'distinct', 'exists:students,id'],
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
        ];
    }
}
