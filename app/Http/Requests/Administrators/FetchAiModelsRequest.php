<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;

final class FetchAiModelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAi', GeneralSetting::class) === true
            || $this->user()?->can('updateAi', GeneralSetting::class) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', 'max:64'],
            'api_key' => ['nullable', 'string', 'max:2048'],
            'base_url' => ['nullable', 'string', 'max:500'],
            'headers' => ['nullable', 'array'],
        ];
    }
}
