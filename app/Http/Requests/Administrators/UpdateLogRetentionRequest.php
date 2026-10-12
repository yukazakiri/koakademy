<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use App\Services\LogRetentionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLogRetentionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateLogRetention', GeneralSetting::class) ?? false;
    }

    public function rules(): array
    {
        $rules = ['policies' => ['required', 'array:'.implode(',', LogRetentionService::SOURCES)]];
        foreach (LogRetentionService::SOURCES as $source) {
            $rules['policies.'.$source] = ['required', 'array:enabled,retention_days,frequency,time'];
            $rules['policies.'.$source.'.enabled'] = ['required', 'boolean'];
            $rules['policies.'.$source.'.retention_days'] = ['required', 'integer', 'min:1', 'max:3650'];
            $rules['policies.'.$source.'.frequency'] = ['required', Rule::in(['daily', 'weekly'])];
            $rules['policies.'.$source.'.time'] = ['required', 'date_format:H:i'];
        }

        return $rules;
    }
}
