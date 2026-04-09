<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CopyClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
