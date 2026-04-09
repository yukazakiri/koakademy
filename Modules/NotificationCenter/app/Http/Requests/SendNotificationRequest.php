<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\NotificationCenter\Models\NotificationTemplate;

final class SendNotificationRequest extends FormRequest
{
    public function rules(): array
    {
        $rules = [
            'target_audience' => array_filter([
                $this->routeIs('administrators.notifications.preview') ? 'nullable' : 'required',
                'array',
                $this->routeIs('administrators.notifications.preview') ? null : 'min:1',
            ]),
            'target_audience.*' => [$this->routeIs('administrators.notifications.preview') ? 'nullable' : 'required', 'string'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', 'in:mail,database,broadcast'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'type' => ['nullable', 'string', 'in:info,success,warning,danger,error'],
            'icon' => ['nullable', 'string', 'max:50'],
            'actions' => ['nullable', 'array'],
            'actions.*.label' => ['required_with:actions', 'string', 'max:50'],
            'actions.*.url' => ['required_with:actions', 'url', 'max:255'],
            'actions.*.color' => ['nullable', 'string', 'in:primary,secondary,success,danger,warning,info,gray'],
            'actions.*.shouldOpenInNewTab' => ['nullable', 'boolean'],
            'template_slug' => ['nullable', 'string', 'exists:notification_templates,slug'],
            'template_data' => ['nullable', 'array'],
        ];

        return $rules;
    }

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->template_slug) {
                $template = NotificationTemplate::findBySlug($this->template_slug);
                if ($template && ! empty($template->variables)) {
                    foreach ($template->variables as $variable) {
                        if (! in_array($variable, ['title', 'content']) && ! $this->has("template_data.{$variable}")) {
                            $validator->errors()->add(
                                "template_data.{$variable}",
                                "The {$variable} field is required for this template."
                            );
                        }
                    }
                }
            }
        });
    }
}
