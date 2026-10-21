<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\FeatureReleaseAudience;
use App\Services\FeatureManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateFeaturePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return FeatureManagementService::canManage($this->user());
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', Rule::in(['legacy', 'enforced'])],
            'release_audience' => ['nullable', Rule::enum(FeatureReleaseAudience::class)],
            'strategy' => ['required', Rule::in(['defaults', 'targeted', 'percentage'])],
            'percentage' => ['required', 'integer', 'between:0,100'],
            'allow_opt_in' => ['required', 'boolean'],
            'activate_at' => ['nullable', 'date'],
            'retire_at' => ['nullable', 'date', Rule::when($this->filled('activate_at'), 'after:activate_at')],
            'revision' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['retire_at.after' => 'Retirement must be after release time.'];
    }
}
