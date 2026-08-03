<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\NewsletterProvider;
use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateNewsletterSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateNewsletter', GeneralSetting::class) === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'provider' => ['required', Rule::enum(NewsletterProvider::class)],
            'providers' => ['required', 'array:sequenzy,brevo,mailchimp'],
            'providers.sequenzy' => ['required', 'array:api_key'],
            'providers.sequenzy.api_key' => ['nullable', 'string', 'max:2048'],
            'providers.brevo' => ['required', 'array:api_key,list_id'],
            'providers.brevo.api_key' => ['nullable', 'string', 'max:2048'],
            'providers.brevo.list_id' => [Rule::requiredIf($this->boolean('enabled') && $this->input('provider') === NewsletterProvider::Brevo->value), 'nullable', 'integer', 'min:1'],
            'providers.mailchimp' => ['required', 'array:api_key,server_prefix,audience_id'],
            'providers.mailchimp.api_key' => ['nullable', 'string', 'max:2048'],
            'providers.mailchimp.server_prefix' => [Rule::requiredIf($this->boolean('enabled') && $this->input('provider') === NewsletterProvider::Mailchimp->value), 'nullable', 'string', 'max:32', 'regex:/^[a-z0-9-]+$/i'],
            'providers.mailchimp.audience_id' => [Rule::requiredIf($this->boolean('enabled') && $this->input('provider') === NewsletterProvider::Mailchimp->value), 'nullable', 'string', 'max:128'],
        ];
    }
}
