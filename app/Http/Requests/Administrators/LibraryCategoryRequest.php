<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\LibrarySystem\Models\Category;

final class LibraryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof Category ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('library_categories', 'name')->ignore($categoryId)],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.unique' => 'This category already exists.',
            'color.regex' => 'Category color must be a valid hex code (e.g. #2563eb).',
        ];
    }
}
