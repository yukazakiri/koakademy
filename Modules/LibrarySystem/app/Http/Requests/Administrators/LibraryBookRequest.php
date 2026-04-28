<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\LibrarySystem\Models\Book;

final class LibraryBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $book = $this->route('book');
        $bookId = $book instanceof Book ? $book->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:50', Rule::unique('library_books', 'isbn')->ignore($bookId)],
            'author_id' => ['required', 'exists:library_authors,id'],
            'category_id' => ['required', 'exists:library_categories,id'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1500', 'max:2100'],
            'pages' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'cover_image_upload' => ['nullable', 'image', 'max:4096'],
            'total_copies' => ['required', 'integer', 'min:1', 'max:10000'],
            'available_copies' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:available,borrowed,maintenance'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Book title is required.',
            'author_id.required' => 'Select an author for this book.',
            'author_id.exists' => 'Selected author could not be found.',
            'category_id.required' => 'Select a category for this book.',
            'category_id.exists' => 'Selected category could not be found.',
            'isbn.unique' => 'This ISBN already exists in the library catalog.',
            'total_copies.required' => 'Total copies is required.',
            'total_copies.min' => 'Total copies must be at least 1.',
            'available_copies.min' => 'Available copies cannot be negative.',
            'status.in' => 'Choose a valid availability status.',
            'cover_image_upload.image' => 'Cover image must be a valid image file.',
            'cover_image_upload.max' => 'Cover image must be 4MB or smaller.',
        ];
    }
}
