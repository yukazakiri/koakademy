<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\StudentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateTuitionPaymentScheduleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('Update:SystemManagementTuitionPaymentSchedule') ?? false;
    }

    public function rules(): array
    {
        return [
            'profiles' => ['required', 'array'],
            'profiles.*.enabled' => ['required', 'boolean'],
            'profiles.*.percentages' => ['required', 'array:prelim,midterm,finals'],
            'profiles.*.percentages.prelim' => ['required', 'numeric', 'min:0', 'max:100'],
            'profiles.*.percentages.midterm' => ['required', 'numeric', 'min:0', 'max:100'],
            'profiles.*.percentages.finals' => ['required', 'numeric', 'min:0', 'max:100'],
            'profiles.*.rounding_increment' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'profiles.*.rounding_mode' => ['required', Rule::in(['nearest', 'down', 'up'])],
            'profiles.*.rounded_terms' => ['required', 'array'],
            'profiles.*.rounded_terms.*' => [Rule::in(['prelim', 'midterm', 'finals'])],
            'profiles.*.remainder_term' => ['required', Rule::in(['prelim', 'midterm', 'finals'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('profiles', []) as $studentType => $profile) {
                if (StudentType::tryFrom((string) $studentType) === null) {
                    $validator->errors()->add("profiles.{$studentType}", 'The student type is invalid.');

                    continue;
                }
                $total = collect($profile['percentages'] ?? [])->sum(fn ($value): float => (float) $value);
                if (abs($total - 100) > 0.001) {
                    $validator->errors()->add("profiles.{$studentType}.percentages", 'The installment percentages must total 100%.');
                }
                if (in_array($profile['remainder_term'] ?? null, $profile['rounded_terms'] ?? [], true)) {
                    $validator->errors()->add("profiles.{$studentType}.rounded_terms", 'The exact remainder term cannot also be rounded.');
                }
            }
        }];
    }
}
