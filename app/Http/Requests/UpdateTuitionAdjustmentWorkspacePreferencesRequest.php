<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTuitionAdjustmentWorkspacePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view_tuition_fees') ?? false;
    }

    public function rules(): array
    {
        return ['layout' => ['required', 'string', Rule::in(['inspector', 'staged'])]];
    }
}
