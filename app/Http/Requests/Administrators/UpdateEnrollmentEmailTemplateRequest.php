<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use App\Services\EnrollmentEmailTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateEnrollmentEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateEmailTemplates', GeneralSetting::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $notificationTypes = array_column(EnrollmentEmailTemplateService::NOTIFICATION_TYPES, 'value');

        return [
            'step_key' => ['required', 'string', 'max:100'],
            'change_notes' => ['nullable', 'string', 'max:255'],
            'configuration' => ['required', 'array'],
            'configuration.notification' => ['required', 'string', Rule::in($notificationTypes)],
            'configuration.subject' => ['nullable', 'string', 'max:255'],
            'configuration.headline' => ['nullable', 'string', 'max:255'],
            'configuration.custom_body' => ['nullable', 'string', 'max:5000'],
            'configuration.button_label' => ['nullable', 'string', 'max:120'],
            'configuration.button_url' => ['nullable', 'string', 'max:2048', 'regex:/^\/(?!\/)/'],
            'configuration.include_details' => ['nullable', 'boolean'],
            'configuration.include_subjects' => ['nullable', 'boolean'],
            'configuration.include_tuition' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'configuration.notification.in' => 'Select one of the supported enrollment email templates.',
            'configuration.button_url.regex' => 'The call-to-action must be a path on this portal, starting with a single "/" (for example /student/applications). Absolute external URLs are not allowed.',
        ];
    }
}
