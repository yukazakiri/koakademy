<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\SchoolLevel;
use App\Models\GeneralSetting;
use App\Support\IsoAlpha2CountryCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreSchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSchool', GeneralSetting::class) ?? false;
    }

    /**
     * @return array<string, array<int, string|Rule>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:schools,name'],
            'code' => ['required', 'string', 'max:50', 'unique:schools,code'],
            'country_code' => IsoAlpha2CountryCodes::nullableRules(),
            'school_level' => ['required', Rule::enum(SchoolLevel::class)],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'dean_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('country_code')) {
            $this->merge(['country_code' => IsoAlpha2CountryCodes::normalize($this->input('country_code'))]);
        }
    }
}
