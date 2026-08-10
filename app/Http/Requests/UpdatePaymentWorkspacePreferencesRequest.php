<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePaymentWorkspacePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('View:Cashier') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'layout' => ['required', 'string', Rule::in(['guided', 'spreadsheet'])],
            'density' => ['required', 'string', Rule::in(['comfortable', 'compact'])],
            'history_visibility' => ['required', 'string', Rule::in(['auto', 'open', 'hidden'])],
            'default_payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ];
    }
}
