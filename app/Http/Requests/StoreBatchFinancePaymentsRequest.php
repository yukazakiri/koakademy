<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class StoreBatchFinancePaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'uuid'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.client_row_id' => ['required', 'string', 'max:64', 'distinct'],
            'rows.*.student_id' => ['required', 'integer'],
            'rows.*.payment_method' => ['required', 'string', 'max:64'],
            'rows.*.reference_number' => ['nullable', 'string', 'max:255'],
            'rows.*.remarks' => ['nullable', 'string', 'max:2000'],
            'rows.*.items' => ['required', 'array', 'min:1', 'max:25'],
            'rows.*.items.*.type' => ['required', 'string', 'max:32'],
            'rows.*.items.*.amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0'],
            'rows.*.items.*.tuition_id' => ['nullable', 'integer'],
            'rows.*.items.*.fee_key' => ['nullable', 'string', 'max:64'],
            'rows.*.items.*.id' => ['nullable', 'integer'],
            'rows.*.items.*.quantity' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
