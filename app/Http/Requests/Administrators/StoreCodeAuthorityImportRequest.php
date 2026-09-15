<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\IndustryCourseCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

final class StoreCodeAuthorityImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', IndustryCourseCode::class)
            || Gate::allows('update', IndustryCourseCode::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $maxMb = max(1, (int) config('authority-codes.import.max_mb', 10));

        return [
            'file' => ['required', File::types(['xlsx', 'xls', 'csv'])->max($maxMb.'mb')],
            'code_authority_id' => ['required', 'integer', 'exists:code_authorities,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code_authority_id.exists' => 'The selected authority does not exist.',
        ];
    }
}
