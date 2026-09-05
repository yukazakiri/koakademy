<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateMobilePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'semester' => ['sometimes', 'integer', 'min:1', 'max:3'],
            'school_year' => ['sometimes', 'string', 'max:20'],
            'active_school_id' => ['sometimes', 'nullable', 'integer', 'exists:schools,id'],
            'theme' => ['sometimes', 'string', 'in:light,dark,system'],
            'notifications_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
