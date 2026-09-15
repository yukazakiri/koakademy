<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\IndustryCourseCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class UpdateIndustryCourseCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', IndustryCourseCode::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code' => ['sometimes', 'required', 'string', 'max:100'],
            'title' => ['sometimes', 'required', 'string', 'max:500'],
            'attributes' => ['nullable', 'array', 'max:100'],
            'attributes.*' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
