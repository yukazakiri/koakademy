<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use App\Services\GeneralSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateApiManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateApi', GeneralSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'public_api_enabled' => ['required', 'boolean'],
            'public_settings_enabled' => ['required', 'boolean'],
            'public_settings_fields' => ['required', 'array'],
            'public_settings_fields.*' => [
                'string',
                Rule::in(array_keys(GeneralSettingsService::publicApiFieldDefinitions())),
            ],
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_description' => ['nullable', 'string'],
            'theme_color' => ['nullable', 'string', 'max:50'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_phone' => ['nullable', 'string', 'max:50'],
            'social_network' => ['nullable', 'array'],
            'social_network.facebook' => ['nullable', 'url', 'max:255'],
            'social_network.instagram' => ['nullable', 'url', 'max:255'],
            'social_network.twitter' => ['nullable', 'url', 'max:255'],
            'social_network.linkedin' => ['nullable', 'url', 'max:255'],
            'social_network.youtube' => ['nullable', 'url', 'max:255'],
            'social_network.tiktok' => ['nullable', 'url', 'max:255'],
            'school_portal_url' => ['nullable', 'url', 'max:255'],
            'school_portal_enabled' => ['required', 'boolean'],
            'online_enrollment_enabled' => ['required', 'boolean'],
            'school_portal_maintenance' => ['required', 'boolean'],
            'school_portal_title' => ['nullable', 'string', 'max:255'],
            'school_portal_description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'public_settings_fields.required' => 'Select at least one field to manage for the public website API.',
            'public_settings_fields.*.in' => 'One of the selected public API fields is not supported.',
        ];
    }
}
