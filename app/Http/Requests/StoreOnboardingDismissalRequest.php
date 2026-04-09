<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreOnboardingDismissalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'feature_key' => ['required', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'feature_key.required' => 'Feature key is required.',
        ];
    }
}
