<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\Faculty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

final class StoreFacultyBulkImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Faculty::class) || Gate::allows('update', Faculty::class);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['file' => ['required', File::types(['xlsx', 'xls', 'mdb'])->max('20mb')]];
    }
}
