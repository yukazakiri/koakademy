<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Department|null $department */
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : null;

        return [
            'school_id' => ['required', 'integer', 'exists:schools,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique('departments', 'code')->ignore($departmentId)],
            'description' => ['nullable', 'string'],
            'head_name' => ['nullable', 'string', 'max:255'],
            'head_email' => ['nullable', 'string', 'email', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'school_id.required' => 'Please select a school.',
            'name.required' => 'Department name is required.',
            'code.required' => 'Department code is required.',
            'code.unique' => 'This department code is already in use.',
            'head_email.email' => 'Please enter a valid email for the department head.',
            'email.email' => 'Please enter a valid department email.',
        ];
    }
}
