<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class BulkForceDeleteBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_ids' => ['required', 'array', 'min:1'],
            'book_ids.*' => ['required', 'integer', 'distinct', 'exists:library_books,id'],
            'confirm_text' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'book_ids.required' => 'Select at least one book.',
            'book_ids.array' => 'Selected books must be a valid list.',
            'book_ids.min' => 'Select at least one book.',
            'book_ids.*.required' => 'Each selected book must be valid.',
            'book_ids.*.integer' => 'Each selected book must be a valid ID.',
            'book_ids.*.distinct' => 'Selected books must be unique.',
            'book_ids.*.exists' => 'One or more selected books do not exist.',
            'confirm_text.required' => 'Type the confirmation text to proceed.',
        ];
    }
}
