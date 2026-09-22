<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Models\GeneralSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateGradingPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateGrading', GeneralSetting::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'input_type' => ['required', 'in:numeric,symbol'],
            'numeric_min' => ['nullable', 'numeric'],
            'numeric_max' => ['nullable', 'numeric', 'gt:numeric_min'],
            'direction' => ['required', 'in:higher_is_better,lower_is_better'],
            'decimal_places' => ['required', 'integer', 'min:0', 'max:6'],
            'include_failed_in_gwa' => ['required', 'boolean'],
            'excluded_keywords' => ['nullable', 'array'],
            'excluded_keywords.*' => ['string', 'max:64'],
            'excluded_subject_ids' => ['nullable', 'array'],
            'excluded_subject_ids.*' => ['integer', 'min:1'],
            'bands' => ['required', 'array', 'min:1', 'max:50'],
            'bands.*.id' => ['nullable', 'string', 'max:64'],
            'bands.*.symbol' => ['nullable', 'string', 'max:32'],
            'bands.*.label' => ['required', 'string', 'max:80'],
            'bands.*.min' => ['nullable', 'numeric'],
            'bands.*.max' => ['nullable', 'numeric'],
            'bands.*.outcome' => ['required', 'in:pass,fail,incomplete,withdrawn,non_credit'],
            'bands.*.quality_points' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'bands.*.color' => ['nullable', 'string', 'max:32'],
            'bands.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'components' => ['required', 'array', 'min:1', 'max:20'],
            'components.*.id' => ['nullable', 'string', 'max:64'],
            'components.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'max:40'],
            'components.*.label' => ['required', 'string', 'max:80'],
            'components.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'components.*.required' => ['required', 'boolean'],
            'components.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $inputType = $this->string('input_type')->toString();
            $bands = collect($this->input('bands', []));
            $components = collect($this->input('components', []));

            if ($inputType === 'numeric') {
                foreach ($bands as $index => $band) {
                    if (! is_numeric($band['min'] ?? null) || ! is_numeric($band['max'] ?? null)) {
                        $validator->errors()->add("bands.{$index}", 'Numeric grading bands require both a minimum and maximum value.');

                        continue;
                    }

                    if ((float) $band['min'] > (float) $band['max']) {
                        $validator->errors()->add("bands.{$index}.max", 'The maximum must be greater than or equal to the minimum.');
                    }
                }

                $orderedBands = $bands->sortBy(fn (array $band): float => (float) ($band['min'] ?? 0))->values();
                foreach ($orderedBands->zip($orderedBands->slice(1)) as [$current, $next]) {
                    if ($next !== null && (float) $current['max'] >= (float) $next['min']) {
                        $validator->errors()->add('bands', 'Numeric grading bands cannot overlap.');
                        break;
                    }
                }
            } else {
                $symbols = $bands->pluck('symbol')->map(fn ($symbol): string => mb_strtoupper(mb_trim((string) $symbol)))->filter();
                if ($symbols->count() !== $symbols->unique()->count()) {
                    $validator->errors()->add('bands', 'Each symbolic grade must be unique.');
                }
                if ($symbols->count() !== $bands->count()) {
                    $validator->errors()->add('bands', 'Symbolic grading bands require a grade symbol.');
                }
            }

            $keys = $components->pluck('key');
            if ($keys->count() !== $keys->unique()->count()) {
                $validator->errors()->add('components', 'Assessment component keys must be unique.');
            }

            $totalWeight = $components->sum(fn (array $component): float => (float) ($component['weight'] ?? 0));
            if (abs($totalWeight - 100) > 0.001) {
                $validator->errors()->add('components', 'Assessment component weights must total 100%.');
            }
        }];
    }
}
