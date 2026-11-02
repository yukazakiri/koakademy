<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\Student;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** School-owned preferences, not deployment-time overrides. */
final class StudentConfigurationService
{
    /** @var array<string, array<string, mixed>> */
    private array $resolved = [];

    public static function defaults(bool $legacy = false): array
    {
        $fields = [];
        foreach (self::fieldLabels() as $key => $label) {
            $fields[$key] = ['label' => $label, 'visible' => $legacy || ! in_array($key, ['religion', 'height', 'weight'], true), 'required' => false];
        }

        return [
            'preset' => $legacy ? 'legacy_ph' : 'neutral',
            'enabled_types' => $legacy ? array_column(StudentType::cases(), 'value') : ['college'],
            'default_type' => 'college',
            'defaults' => ['nationality' => $legacy ? 'filipino' : '', 'citizenship' => $legacy ? 'filipino' : '', 'gender' => $legacy ? 'male' : ''],
            'philippine_reporting' => $legacy,
            'fields' => $fields,
            'external_identifier' => [
                'enabled' => $legacy, 'label' => $legacy ? 'LRN' : 'External Identifier',
                'help_text' => $legacy ? 'Learner Reference Number assigned by the Department of Education.' : 'An identifier assigned outside this institution.',
                'required' => $legacy, 'student_types' => ['shs'], 'format' => 'text',
                'min_length' => 1, 'max_length' => $legacy ? 20 : 32, 'use_as_student_id' => $legacy,
            ],
            'type_labels' => $legacy
                ? collect(StudentType::cases())->mapWithKeys(fn (StudentType $type): array => [$type->value => $type->getLabel()])->all()
                : ['college' => 'Higher Education Student', 'shs' => 'Upper Secondary Student', 'tesda' => 'Vocational / Training Student', 'dhrt' => 'Diploma Student'],
            'year_levels' => [
                'college' => [['value' => 1, 'label' => $legacy ? '1st year' : '1st Year'], ['value' => 2, 'label' => $legacy ? '2nd year' : '2nd Year'], ['value' => 3, 'label' => $legacy ? '3rd year' : '3rd Year'], ['value' => 4, 'label' => $legacy ? '4th year' : '4th Year'], ['value' => 5, 'label' => $legacy ? 'Graduate' : '5th Year']],
                'shs' => [['value' => 11, 'label' => 'Grade 11'], ['value' => 12, 'label' => 'Grade 12']],
                'tesda' => collect(range(1, 5))->map(fn (int $level): array => ['value' => $level, 'label' => "Training Level {$level}"])->all(),
                'dhrt' => collect(range(1, 5))->map(fn (int $level): array => ['value' => $level, 'label' => "Year {$level}"])->all(),
            ],
        ];
    }

    public static function fieldLabels(): array
    {
        return ['email' => 'Email', 'phone' => 'Phone', 'civil_status' => 'Civil Status', 'nationality' => 'Nationality', 'citizenship' => 'Citizenship', 'religion' => 'Religion', 'height' => 'Height', 'weight' => 'Weight', 'birthplace' => 'Birthplace', 'current_address' => 'Current Address', 'permanent_address' => 'Permanent Address'];
    }

    public static function reportingFields(): array
    {
        return ['region_of_origin', 'province_of_origin', 'city_of_origin', 'ethnicity', 'is_indigenous_person', 'indigenous_group', 'is_pwd', 'pwd_type', 'is_solo_parent', 'is_senior_citizen', 'is_magna_carta', 'is_underprivileged', 'is_first_generation', 'income_bracket_mode', 'use_same_parent_income', 'family_income_bracket', 'father_income_bracket', 'mother_income_bracket'];
    }

    public static function scope(?int $schoolId): string
    {
        return $schoolId === null ? 'installation' : 'school:'.$schoolId;
    }

    public function schoolId(): ?int
    {
        return app(TenantContext::class)->getCurrentSchoolId();
    }

    /** Public registration can choose a school only when it is unambiguous. */
    public function resolvePublicSchool(?int $courseId = null): void
    {
        $tenant = app(TenantContext::class);
        if ($tenant->getCurrentSchoolId() !== null) {
            return;
        }
        $schools = \App\Models\School::query()->where('is_active', true)->orderBy('id')->limit(2)->get();
        if ($schools->count() === 1) {
            $tenant->setCurrentSchool($schools->first());

            return;
        }
        if ($courseId !== null) {
            $course = \App\Models\Course::withoutSchoolScope()->with('department')->find($courseId);
            $schoolId = $course?->school_id ?? $course?->department?->school_id;
            $school = $schoolId !== null ? \App\Models\School::query()->where('is_active', true)->find($schoolId) : null;
            if ($school !== null) {
                $tenant->setCurrentSchool($school);
            }
        }
    }

    /** Validate a settings draft against generation without consuming any numbers. */
    public function validateIdentifierCompatibility(array $configuration): void
    {
        $this->forget();
        $generator = app(IdentifierGenerator::class);
        $sequence = $generator->sequenceFor(IdentifierGenerator::Student);
        if ($sequence->assignment_mode === 'manual') {
            return;
        }
        $prefixes = [];
        foreach ($configuration['enabled_types'] as $value) {
            $external = $configuration['external_identifier'];
            if ($external['enabled'] && $external['use_as_student_id'] && in_array($value, $external['student_types'], true)) {
                continue;
            }
            $prefixes[] = $generator->resolvePrefix($sequence, StudentType::from($value));
            $counter = $generator->sequenceFor(IdentifierGenerator::Student, type: StudentType::from($value));
            $generator->assertPolicyFormat($sequence, StudentType::from($value), $counter->next_number);
        }
        if ($sequence->counter_scope === 'by_type' && count($prefixes) !== count(array_unique($prefixes))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['enabled_types' => 'These categories would share an identifier prefix with separate counters. Configure distinct category prefixes in Identifiers first.']);
        }
    }

    public function get(?int $schoolId = null): array
    {
        $schoolId ??= $this->schoolId();
        $scope = self::scope($schoolId);
        if (isset($this->resolved[$scope])) {
            return $this->resolved[$scope];
        }
        $stored = DB::table('student_configurations')->where('scope', $scope)->value('configuration');
        $configuration = is_string($stored) ? json_decode($stored, true, flags: JSON_THROW_ON_ERROR) : [];

        // Arrays such as enabled_types are replaced, never recursively merged by index.
        return $this->resolved[$scope] = array_replace(self::defaults(), $configuration);
    }

    public function update(array $configuration, ?int $schoolId = null): void
    {
        $schoolId ??= $this->schoolId();
        DB::table('student_configurations')->upsert([
            ['scope' => self::scope($schoolId), 'school_id' => $schoolId, 'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now()],
        ], ['scope'], ['configuration', 'updated_at']);
        unset($this->resolved[self::scope($schoolId)]);
    }

    /** Invalidate a request-local snapshot after taking the identifier lock. */
    public function forget(?int $schoolId = null): void
    {
        unset($this->resolved[self::scope($schoolId ?? $this->schoolId())]);
    }

    public function externalFor(string $type, ?int $schoolId = null): array
    {
        $external = $this->get($schoolId)['external_identifier'];
        $external['enabled'] = $external['enabled'] && in_array($type, $external['student_types'], true);

        return $external;
    }

    public function usesExternal(string $type, ?int $schoolId = null): bool
    {
        $external = $this->externalFor($type, $schoolId);

        return $external['enabled'] && $external['use_as_student_id'];
    }

    public function validatePartialUpdate(array $attributes, Student $student): void
    {
        $rules = $this->rules([
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'religion' => ['nullable', 'string', 'max:50'],
        ], $attributes['student_type'] ?? $student->student_type->value, $student);
        foreach (array_keys($attributes) as $field) {
            if (! $this->fieldIsVisible($field, $student->school_id)) {
                throw \Illuminate\Validation\ValidationException::withMessages([$field => 'This field is disabled by the school configuration.']);
            }
        }
        $rules = array_intersect_key($rules, $attributes);
        \Illuminate\Support\Facades\Validator::make($attributes, $rules)->validate();
    }

    /** Reject creation when an entry point cannot collect a configured required field. */
    public function assertRequiredFieldsSupported(array $supportedFields, ?int $schoolId = null): void
    {
        foreach ($this->get($schoolId)['fields'] as $field => $policy) {
            if ($policy['visible'] && $policy['required'] && ! in_array($field, $supportedFields, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => $policy['label'].' is required by this school. Create this student using the administrator form.',
                ]);
            }
        }
    }

    public function fieldIsVisible(string $field, ?int $schoolId = null): bool
    {
        $configuration = $this->get($schoolId);

        return ($configuration['fields'][$field]['visible'] ?? true)
            && ($configuration['philippine_reporting'] || ! in_array($field, self::reportingFields(), true));
    }

    /** Apply configurable rules to the existing form contract. Core identity stays mandatory. */
    public function rules(array $rules, string $type, ?Student $student = null, ?int $schoolId = null): array
    {
        $schoolId = $student?->school_id ?? $schoolId ?? $this->schoolId();
        $config = $this->get($schoolId);
        $allowed = $config['enabled_types'];
        if ($student !== null && $student->student_type !== null) {
            $allowed[] = $student->student_type->value;
        }
        $rules['student_type'] = ['required', Rule::in(array_unique($allowed))];
        $levels = array_column($config['year_levels'][$type] ?? [], 'value');
        if ($student !== null && $student->student_type?->value === $type) {
            $levels[] = $student->academic_year;
        }
        $rules['academic_year'] = ['required', 'integer', Rule::in($levels)];

        foreach ($config['fields'] as $field => $policy) {
            if (! isset($rules[$field])) {
                continue;
            }
            if (! $policy['visible']) {
                $rules[$field] = ['exclude'];
            } elseif ($policy['required']) {
                $rules[$field] = ['required', ...array_diff($rules[$field], ['nullable'])];
            }
        }
        if (! $config['philippine_reporting']) {
            foreach (self::reportingFields() as $field) {
                if (isset($rules[$field])) {
                    $rules[$field] = ['exclude'];
                }
            }
        }

        $external = $this->externalFor($type, $schoolId);
        if ($student !== null && $type === $student->student_type?->value) {
            $external['required'] = false; // Do not demand a newly enabled identifier on unrelated legacy edits.
            $external['use_as_student_id'] = $external['use_as_student_id'] && (string) $student->student_id === (string) $student->lrn;
        }
        $rules['lrn'] = $external['enabled'] ? [
            'bail',
            $external['required'] || $external['use_as_student_id'] ? 'required' : 'nullable',
            'string', 'min:'.$external['min_length'], 'max:'.$external['max_length'],
            ...($external['format'] === 'digits' ? ['regex:/\A[0-9]+\z/'] : []),
            Rule::unique('students', 'lrn')->where('school_id', $schoolId)->ignore($student?->id),
        ] : ['exclude'];
        if ($external['enabled'] && $external['use_as_student_id'] && ($student === null || (string) $student->student_id === (string) $student->lrn)) {
            $rules['lrn'][] = Rule::unique('students', 'student_id')->where('school_id', $schoolId)->ignore($student?->id);
        }
        if ($external['enabled'] && $student !== null && $type === $student->student_type?->value && $student->lrn !== null) {
            $configuredRules = $rules['lrn'];
            $rules['lrn'] = [$external['use_as_student_id'] ? 'required' : 'nullable', function (string $attribute, mixed $value, Closure $fail) use ($student, $configuredRules): void {
                if ((string) $value !== (string) $student->lrn) {
                    $validator = \Illuminate\Support\Facades\Validator::make(['lrn' => $value], ['lrn' => $configuredRules]);
                    if ($validator->fails()) {
                        $fail($validator->errors()->first('lrn'));
                    }
                }
            }];
        }

        return $rules;
    }

    /** Hidden form controls must not clear previously stored values. */
    public function preserveHidden(array $data, Student $student): array
    {
        $config = $this->get($student->school_id);
        foreach ($config['fields'] as $field => $policy) {
            if (! $policy['visible']) {
                $data[$field] = match ($field) {
                    'citizenship', 'height', 'weight', 'birthplace', 'current_address', 'permanent_address' => $student->personalInfo?->{match ($field) {
                        'current_address' => 'current_adress', default => $field
                    }} ?? data_get($student->contacts, 'personal_info.'.$field) ?? ($field === 'current_address' ? $student->address : null),
                    default => $student->{$field},
                };
            }
        }
        if (! $this->externalFor((string) $data['student_type'], $student->school_id)['enabled']) {
            $data['lrn'] = $student->lrn;
        }
        if (! $config['philippine_reporting']) {
            foreach (self::reportingFields() as $field) {
                $data[$field] = $student->{$field};
            }
        }

        return $data;
    }
}
