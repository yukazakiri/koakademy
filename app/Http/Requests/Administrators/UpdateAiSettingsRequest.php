<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateAi', GeneralSetting::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'primary_provider' => ['required', 'string', 'max:64'],
            'fallback_provider' => ['nullable', 'string', 'max:64'],
            'failover_enabled' => ['required', 'boolean'],
            'request_timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'providers' => ['required', 'array'],
            'providers.*.enabled' => ['sometimes', 'boolean'],
            'providers.*.api_key' => ['nullable', 'string', 'max:2048'],
            'providers.*.base_url' => ['nullable', 'string', 'max:500'],
            'providers.*.default_chat_model' => ['nullable', 'string', 'max:255'],
            'providers.*.default_fast_model' => ['nullable', 'string', 'max:255'],
            'providers.*.default_embeddings_model' => ['nullable', 'string', 'max:255'],
            'providers.*.custom_models' => ['nullable', 'array'],
            'providers.*.custom_models.*' => ['string', 'max:255'],
            'custom_providers' => ['nullable', 'array'],
            'custom_providers.*.key' => ['required_with:custom_providers', 'string', 'max:64'],
            'custom_providers.*.label' => ['required_with:custom_providers', 'string', 'max:255'],
            'custom_providers.*.enabled' => ['sometimes', 'boolean'],
            'custom_providers.*.api_key' => ['nullable', 'string', 'max:2048'],
            'custom_providers.*.base_url' => ['required_with:custom_providers', 'string', 'max:500'],
            'custom_providers.*.headers' => ['nullable', 'array'],
            'custom_providers.*.default_chat_model' => ['nullable', 'string', 'max:255'],
            'custom_providers.*.default_fast_model' => ['nullable', 'string', 'max:255'],
            'custom_providers.*.default_embeddings_model' => ['nullable', 'string', 'max:255'],
            'custom_providers.*.custom_models' => ['nullable', 'array'],
            'custom_providers.*.custom_models.*' => ['string', 'max:255'],
        ];
    }
}
