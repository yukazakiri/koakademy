<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\InventoryBorrowingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InventoryBorrowingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                Rule::exists('inventory_products', 'id')
                    ->where('is_borrowable', true)
                    ->where('is_active', true),
            ],
            'quantity_borrowed' => ['required', 'integer', 'min:1'],
            'borrower_name' => ['required', 'string', 'max:255'],
            'borrower_email' => ['nullable', 'email', 'max:255'],
            'borrower_phone' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'purpose' => ['nullable', 'string'],
            'status' => ['required', Rule::in(InventoryBorrowingStatus::values())],
            'borrowed_date' => ['required', 'date'],
            'expected_return_date' => ['nullable', 'date', 'after_or_equal:borrowed_date'],
            'actual_return_date' => ['nullable', 'date', 'after_or_equal:borrowed_date'],
            'quantity_returned' => ['nullable', 'integer', 'min:0', 'lte:quantity_borrowed'],
            'quantity_returned_good' => ['nullable', 'integer', 'min:0', 'lte:quantity_borrowed'],
            'quantity_returned_defective' => ['nullable', 'integer', 'min:0', 'lte:quantity_borrowed'],
            'return_notes' => ['nullable', 'string'],
            'issued_by' => ['required', 'exists:users,id'],
            'returned_to' => ['nullable', 'exists:users,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Select a borrowable item.',
            'product_id.exists' => 'Selected item could not be found.',
            'quantity_borrowed.required' => 'Borrowed quantity is required.',
            'quantity_borrowed.min' => 'Borrowed quantity must be at least 1.',
            'borrower_name.required' => 'Borrower name is required.',
            'status.in' => 'Select a valid borrowing status.',
            'borrowed_date.required' => 'Borrowed date is required.',
            'quantity_returned.lte' => 'Returned quantity cannot exceed borrowed quantity.',
            'quantity_returned_good.lte' => 'Returned good quantity cannot exceed borrowed quantity.',
            'quantity_returned_defective.lte' => 'Returned defective quantity cannot exceed borrowed quantity.',
            'issued_by.required' => 'Select the staff member who issued the item.',
        ];
    }
}
