<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\CurriculumFramework;
use App\Enums\SchoolLevel;
use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateSchoolCurriculumCapabilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateSchool', GeneralSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'capabilities' => ['present', 'array', 'max:10'],
            'capabilities.*.school_level' => ['required', Rule::enum(SchoolLevel::class)],
            'capabilities.*.curriculum_framework' => ['required', Rule::enum(CurriculumFramework::class)],
            'capabilities.*.curriculum_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ($this->input('capabilities', []) as $index => $capability) {
                $schoolLevel = SchoolLevel::tryFrom((string) ($capability['school_level'] ?? ''));
                $framework = CurriculumFramework::tryFrom((string) ($capability['curriculum_framework'] ?? ''));

                if ($schoolLevel !== null && $framework !== null && ! in_array($schoolLevel, $framework->schoolLevels(), true)) {
                    $validator->errors()->add("capabilities.$index.curriculum_framework", 'This curriculum framework is not compatible with the selected school level.');
                }
            }
        }];
    }
}
