<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use Illuminate\Foundation\Http\FormRequest;

final class LibraryBorrowRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'exists:library_books,id'],
            'user_id' => ['required', 'exists:users,id'],
            'borrowed_at' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:borrowed_at'],
            'returned_at' => ['nullable', 'date', 'after_or_equal:borrowed_at'],
            'status' => ['required', 'in:borrowed,returned,lost'],
            'fine_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'book_id.required' => 'Select a book for this borrow record.',
            'book_id.exists' => 'Selected book could not be found.',
            'user_id.required' => 'Select a borrower for this record.',
            'user_id.exists' => 'Selected borrower could not be found.',
            'borrowed_at.required' => 'Borrowed date is required.',
            'due_date.required' => 'Due date is required.',
            'due_date.after_or_equal' => 'Due date must be on or after the borrowed date.',
            'returned_at.after_or_equal' => 'Returned date must be on or after the borrowed date.',
            'status.in' => 'Choose a valid borrow status.',
        ];
    }
}
