<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSchoolRequest extends FormRequest
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
        $school = $this->route('school');
        $schoolId = is_object($school) ? $school->id : $school;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('schools', 'name')->ignore($schoolId)],
            'code' => ['required', 'string', 'max:50', Rule::unique('schools', 'code')->ignore($schoolId)],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'dean_name' => ['nullable', 'string', 'max:255'],
            'dean_email' => ['nullable', 'email', 'max:255'],
        ];
    }
}
