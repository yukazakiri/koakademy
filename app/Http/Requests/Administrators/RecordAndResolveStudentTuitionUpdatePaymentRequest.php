<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

final class RecordAndResolveStudentTuitionUpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage_tuition_fees') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'reference_number' => ['required', 'string', 'max:255'],
            'resolution_note' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }
}
