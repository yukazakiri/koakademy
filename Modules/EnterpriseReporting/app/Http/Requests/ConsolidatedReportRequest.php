<?php

declare(strict_types=1);

namespace Modules\EnterpriseReporting\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ConsolidatedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('ViewAny:StudentEnrollment');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'school_ids' => ['sometimes', 'array', 'min:1', 'max:50'],
            'school_ids.*' => ['required', 'integer', 'distinct', 'min:1'],
            'school_year' => ['sometimes', 'required', 'string', 'regex:/^\d{4}\s*-\s*\d{4}$/D', 'max:20'],
            'semester' => ['sometimes', 'required', 'integer', 'between:1,3'],
        ];
    }
}
