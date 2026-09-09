<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\IndustryCourseCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class StoreIndustryCourseCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', IndustryCourseCode::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'code_authority_id' => ['required', 'integer', 'exists:code_authorities,id'],
            'code' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:500'],
            'category_code' => ['nullable', 'string', 'max:50'],
            'category_name' => ['nullable', 'string', 'max:255'],
            'attributes' => ['nullable', 'array', 'max:100'],
            'attributes.*' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
