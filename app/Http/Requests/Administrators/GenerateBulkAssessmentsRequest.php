<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GenerateBulkAssessmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'course_id' => ['nullable', 'integer', Rule::exists('courses', 'id')],
            'year_level' => ['nullable', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'student_limit' => ['nullable', 'integer', Rule::in([10, 25, 50, 100, 250, 500])],
            'include_deleted' => ['required', 'boolean'],
        ];
    }
}
