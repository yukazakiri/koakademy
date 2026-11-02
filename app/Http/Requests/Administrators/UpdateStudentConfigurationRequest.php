<?php

declare(strict_types=1);

namespace App\Http\Requests\Administrators;

use App\Enums\StudentType;
use App\Models\GeneralSetting;
use App\Services\StudentConfigurationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateStudentConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateStudentConfiguration', GeneralSetting::class) ?? false;
    }

    public function rules(): array
    {
        $types = array_column(StudentType::cases(), 'value');
        $rules = [
            'preset' => ['required', Rule::in(['neutral', 'legacy_ph', 'custom'])],
            'enabled_types' => ['required', 'array', 'min:1'],
            'enabled_types.*' => ['required', 'distinct', Rule::in($types)],
            'default_type' => ['required', Rule::in($types)],
            'defaults' => ['required', 'array:nationality,citizenship,gender'],
            'defaults.nationality' => ['nullable', 'string', 'max:50'],
            'defaults.citizenship' => ['nullable', 'string', 'max:50'],
            'defaults.gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'philippine_reporting' => ['required', 'boolean'],
            'fields' => ['required', 'array:'.implode(',', array_keys(StudentConfigurationService::fieldLabels()))],
            'custom_fields' => ['sometimes', 'array', 'max:30'],
            'custom_fields.*' => ['required', 'array:key,label,type,active,required,options'],
            'custom_fields.*.key' => ['required', 'string', 'max:64', 'distinct', 'regex:/\A[a-z][a-z0-9_]*\z/'],
            'custom_fields.*.label' => ['required', 'string', 'max:80', 'distinct'],
            'custom_fields.*.type' => ['required', Rule::in(['text', 'number', 'date', 'choice'])],
            'custom_fields.*.active' => ['required', 'boolean'],
            'custom_fields.*.required' => ['required', 'boolean'],
            'custom_fields.*.options' => ['present', 'array', 'max:30'],
            'custom_fields.*.options.*' => ['required', 'string', 'max:80'],
            'external_identifier' => ['required', 'array:enabled,label,help_text,required,student_types,format,min_length,max_length,use_as_student_id'],
            'external_identifier.enabled' => ['required', 'boolean'],
            'external_identifier.label' => ['required', 'string', 'max:80'],
            'external_identifier.help_text' => ['nullable', 'string', 'max:500'],
            'external_identifier.required' => ['required', 'boolean'],
            'external_identifier.student_types' => ['present', 'array'],
            'external_identifier.student_types.*' => ['required', 'distinct', Rule::in($types)],
            'external_identifier.format' => ['required', Rule::in(['text', 'digits'])],
            'external_identifier.min_length' => ['required', 'integer', 'min:1', 'max:32'],
            'external_identifier.max_length' => ['required', 'integer', 'gte:external_identifier.min_length', 'max:32'],
            'external_identifier.use_as_student_id' => ['required', 'boolean'],
            'type_labels' => ['required', 'array:'.implode(',', $types)],
            'year_levels' => ['required', 'array:'.implode(',', $types)],
        ];
        foreach (StudentConfigurationService::fieldLabels() as $field => $label) {
            $rules['fields.'.$field] = ['required', 'array:label,visible,required'];
            $rules['fields.'.$field.'.label'] = ['required', 'string', 'max:80'];
            $rules['fields.'.$field.'.visible'] = ['required', 'boolean'];
            $rules['fields.'.$field.'.required'] = ['required', 'boolean'];
        }
        foreach ($types as $type) {
            $rules['type_labels.'.$type] = ['required', 'string', 'max:80'];
            $rules['year_levels.'.$type] = ['required', 'array', 'min:1', 'max:20'];
            $rules['year_levels.'.$type.'.*'] = ['required', 'array:value,label'];
            $rules['year_levels.'.$type.'.*.value'] = ['required', 'integer', 'min:1', 'max:99', 'distinct'];
            $rules['year_levels.'.$type.'.*.label'] = ['required', 'string', 'max:80'];
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if (! in_array($this->input('default_type'), $this->input('enabled_types'), true)) {
                $validator->errors()->add('default_type', 'The default student category must be enabled.');
            }
            foreach ($this->input('fields') as $key => $field) {
                if ($field['required'] && ! $field['visible']) {
                    $validator->errors()->add('fields.'.$key.'.required', 'A hidden field cannot be required.');
                }
            }
            $existing = collect(app(StudentConfigurationService::class)->get()['custom_fields'])->keyBy('key');
            $submitted = collect($this->input('custom_fields', []))->keyBy('key');
            if ($this->has('custom_fields') && $existing->keys()->diff($submitted->keys())->isNotEmpty()) {
                $validator->errors()->add('custom_fields', 'Archive existing fields instead of removing them so saved values remain available.');
            }
            foreach ($this->input('custom_fields', []) as $index => $field) {
                if (in_array('__none__', $field['options'], true)) {
                    $validator->errors()->add("custom_fields.{$index}.options", 'The option value __none__ is reserved.');
                }
                if (count($field['options']) !== count(array_unique($field['options']))) {
                    $validator->errors()->add("custom_fields.{$index}.options", 'Each choice must be unique within this field.');
                }
                if ($field['type'] === 'choice' && $field['active'] && count($field['options']) === 0) {
                    $validator->errors()->add("custom_fields.{$index}.options", 'Add at least one choice.');
                }
                if (! $field['active'] && $field['required']) {
                    $validator->errors()->add("custom_fields.{$index}.required", 'An archived field cannot be required.');
                }
                if (isset($existing[$field['key']]) && $existing[$field['key']]['type'] !== $field['type']) {
                    $validator->errors()->add("custom_fields.{$index}.type", 'The type of an existing field cannot be changed; archive it and create another field.');
                }
            }
            if ($this->boolean('external_identifier.enabled') && $this->input('external_identifier.student_types') === []) {
                $validator->errors()->add('external_identifier.student_types', 'Choose at least one category for this identifier.');
            }
        }];
    }
}
