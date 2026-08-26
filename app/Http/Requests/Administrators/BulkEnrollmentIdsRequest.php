<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class BulkEnrollmentIdsRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
