<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use App\Services\LogRetentionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CleanSystemLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateLogRetention', GeneralSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(LogRetentionService::SOURCES)],
            'mode' => ['required', Rule::in(['prune', 'purge'])],
            'confirmation' => ['required', Rule::in([$this->input('mode') === 'purge' ? 'PURGE' : 'PRUNE'])],
        ];
    }
}
