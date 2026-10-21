<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AdministratorPermissionChecker;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CopyClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AdministratorPermissionChecker::userHasAnyPermission(
            $this->user() instanceof User ? $this->user() : null,
            'Create:Classes',
            [UserRole::Admin],
        );
    }

    /**
     * @return array<string, list<string|Rule>>
     */
    public function rules(): array
    {
        return [
            'section' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
        ];
    }
}
