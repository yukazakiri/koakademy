<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Email\EmailTemplateCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class EnrollmentEmailTemplateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    final public function rules(): array
    {
        return [
            'step_key' => ['required', 'string', 'max:100'],
            'change_notes' => ['nullable', 'string', 'max:255'],
            'configuration' => ['required', 'array:notification,subject,headline,custom_body,button_label,button_url,include_details,include_subjects,include_tuition'],
            'configuration.notification' => ['required', 'string', Rule::in(EmailTemplateCatalog::ENROLLMENT_KEYS)],
            'configuration.subject' => ['nullable', 'string', 'max:255'],
            'configuration.headline' => ['nullable', 'string', 'max:255'],
            'configuration.custom_body' => ['nullable', 'string', 'max:5000'],
            'configuration.button_label' => ['nullable', 'string', 'max:120'],
            // Backslashes and control characters can turn a path into an external
            // authority in browsers. Reject their percent-encoded forms as well.
            'configuration.button_url' => ['nullable', 'string', 'max:2048', 'regex:~^/(?!/)[^\x00-\x20\x7f\\\\]*\z~u', 'not_regex:~%(?:5c|0[0-9a-f]|1[0-9a-f]|7f)~i'],
            'configuration.include_details' => ['nullable', 'boolean'],
            'configuration.include_subjects' => ['nullable', 'boolean'],
            'configuration.include_tuition' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    final public function messages(): array
    {
        $pathMessage = 'The call-to-action must be a path on this portal, starting with a single "/" (for example /student/applications). Absolute external URLs are not allowed.';

        return [
            'configuration.notification.in' => 'Select one of the supported enrollment email templates.',
            'configuration.button_url.regex' => $pathMessage,
            'configuration.button_url.not_regex' => $pathMessage,
        ];
    }

    protected function prepareForValidation(): void
    {
        $configuration = $this->input('configuration');

        if (is_array($configuration) && ($configuration['notification'] ?? null) === 'official_enrollment') {
            $configuration['notification'] = 'assessment';
            $this->merge(['configuration' => $configuration]);
        }
    }
}
