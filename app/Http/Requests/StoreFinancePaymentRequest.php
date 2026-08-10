<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final class StoreFinancePaymentRequest extends FormRequest
{
    /** @var list<string> */
    private const FEE_KEYS = [
        'registration_fee',
        'miscelanous_fee',
        'diploma_or_certificate',
        'transcript_of_records',
        'certification',
        'special_exam',
        'others',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canAccessAdminPortal();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'reference_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*.type' => ['required', 'string', Rule::in(['tuition', 'fee', 'item'])],
            'items.*.amount' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0'],
            'items.*.tuition_id' => ['nullable', 'integer'],
            'items.*.fee_key' => ['nullable', 'string', Rule::in(self::FEE_KEYS)],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $path = "items.{$index}";

                match ($item['type'] ?? null) {
                    'tuition' => $this->requireFields($validator, $path, $item, ['tuition_id', 'amount']),
                    'fee' => $this->requireFields($validator, $path, $item, ['fee_key', 'amount']),
                    'item' => $this->requireFields($validator, $path, $item, ['id']),
                    default => null,
                };
            }
        }];
    }

    /** @param array<string, mixed> $item @param list<string> $fields */
    private function requireFields(Validator $validator, string $path, array $item, array $fields): void
    {
        foreach ($fields as $field) {
            if (! filled($item[$field] ?? null)) {
                $validator->errors()->add("{$path}.{$field}", 'This charge is incomplete.');
            }
        }
    }
}
