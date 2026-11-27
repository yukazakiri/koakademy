<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StudentType;
use App\Models\Student;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

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
            'documents' => self::defaultDocuments($legacy),
            'custom_fields' => [],
            'external_identifier' => [
                'enabled' => $legacy,
                'label' => $legacy ? 'LRN' : 'External Identifier',
                'help_text' => $legacy ? 'Learner Reference Number assigned by the Department of Education.' : 'An identifier assigned outside this institution.',
                'placeholder' => $legacy ? 'e.g. 123456789012' : null,
                'required' => $legacy,
                'student_types' => ['shs'],
                'format' => 'text',
                'min_length' => 1,
                'max_length' => $legacy ? 20 : 32,
                'use_as_student_id' => $legacy,
                'allow_for_signup' => true,
            ],
            'signup' => [
                'allow_student_self_signup' => true,
                'allow_faculty_self_signup' => true,
                'allow_identifier_recovery' => true,
                'require_faculty_id' => false,
                'require_password_on_social_signup' => false,
                'student_identifier_placeholder' => null,
                'student_identifier_help_text' => null,
                'faculty_identifier_placeholder' => null,
                'faculty_identifier_help_text' => null,
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

    /**
     * Built-in international & regional registry presets for open-source deployments worldwide.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function externalIdentifierPresets(): array
    {
        return [
            'none' => [
                'name' => 'Institutional ID Only (Universal)',
                'region' => 'Global Default',
                'description' => 'Use your school-issued Student ID only, with no external government registry number.',
                'config' => [
                    'enabled' => false,
                    'label' => 'External Identifier',
                    'help_text' => 'An identifier assigned outside this institution.',
                    'placeholder' => null,
                    'student_types' => ['shs'],
                    'format' => 'text',
                    'min_length' => 1,
                    'max_length' => 32,
                    'required' => false,
                    'use_as_student_id' => false,
                    'allow_for_signup' => true,
                ],
            ],
            'ph_lrn' => [
                'name' => 'Philippines — DepEd LRN',
                'region' => 'Philippines',
                'description' => '12-digit Learner Reference Number assigned by the Department of Education.',
                'config' => [
                    'enabled' => true,
                    'label' => 'LRN',
                    'help_text' => '12-digit Learner Reference Number assigned by the Department of Education.',
                    'placeholder' => 'e.g. 123456789012',
                    'student_types' => ['shs'],
                    'format' => 'digits',
                    'min_length' => 12,
                    'max_length' => 12,
                    'required' => false,
                    'use_as_student_id' => false,
                    'allow_for_signup' => true,
                ],
            ],
            'uk_uln' => [
                'name' => 'United Kingdom — ULN',
                'region' => 'United Kingdom',
                'description' => '10-digit Unique Learner Number issued by the Learning Records Service.',
                'config' => [
                    'enabled' => true,
                    'label' => 'ULN',
                    'help_text' => '10-digit Unique Learner Number issued by the Learning Records Service.',
                    'placeholder' => 'e.g. 1234567890',
                    'student_types' => ['college', 'shs'],
                    'format' => 'digits',
                    'min_length' => 10,
                    'max_length' => 10,
                    'required' => false,
                    'use_as_student_id' => false,
                    'allow_for_signup' => true,
                ],
            ],
            'au_usi' => [
                'name' => 'Australia — USI',
                'region' => 'Australia',
                'description' => '10-character alphanumeric Unique Student Identifier for tertiary and VET learners.',
                'config' => [
                    'enabled' => true,
                    'label' => 'USI',
                    'help_text' => '10-character Unique Student Identifier issued by the Australian Government.',
                    'placeholder' => 'e.g. 3AW88YH9U5',
                    'student_types' => ['college', 'tesda'],
                    'format' => 'alphanumeric',
                    'min_length' => 10,
                    'max_length' => 10,
                    'required' => false,
                    'use_as_student_id' => false,
                    'allow_for_signup' => true,
                ],
            ],
            'in_apaar' => [
                'name' => 'India — APAAR / ABC ID',
                'region' => 'India',
                'description' => '12-digit Automated Permanent Academic Account Registry (APAAR) ID.',
                'config' => [
                    'enabled' => true,
                    'label' => 'APAAR ID',
                    'help_text' => '12-digit APAAR / Academic Bank of Credits identifier.',
                    'placeholder' => 'e.g. 123456789012',
                    'student_types' => ['college', 'shs'],
                    'format' => 'digits',
                    'min_length' => 12,
                    'max_length' => 12,
                    'required' => false,
                    'use_as_student_id' => false,
                    'allow_for_signup' => true,
                ],
            ],
            'us_ssid' => [
                'name' => 'United States — State Student ID (SSID)',
                'region' => 'United States',
                'description' => 'Statewide Student Identifier assigned by a state education agency.',
                'config' => [
                    'enabled' => true,
                    'label' => 'State Student ID',
                    'help_text' => 'Statewide Student Identifier (SSID) issued by your state education agency.',
                    'placeholder' => 'e.g. 1048291042',
                    'student_types' => ['shs'],
                    'format' => 'alphanumeric',
                    'min_length' => 6,
                    'max_length' => 20,
                    'required' => false,
                    'use_as_student_id' => false,
                    'allow_for_signup' => true,
                ],
            ],
        ];
    }

    public static function fieldLabels(): array
    {
        return ['email' => 'Email', 'phone' => 'Phone', 'civil_status' => 'Civil Status', 'nationality' => 'Nationality', 'citizenship' => 'Citizenship', 'religion' => 'Religion', 'height' => 'Height', 'weight' => 'Weight', 'birthplace' => 'Birthplace', 'current_address' => 'Current Address', 'permanent_address' => 'Permanent Address'];
    }

    /**
     * @return list<string>
     */
    public static function documentSlotKeys(): array
    {
        return ['birth_certificate', 'form_138', 'good_moral_cert', 'picture_1x1'];
    }

    /**
     * @return array<string, array{label: string, description: string, visible: bool, required: bool}>
     */
    public static function defaultDocuments(bool $legacy = false): array
    {
        if ($legacy) {
            return [
                'birth_certificate' => [
                    'label' => 'Birth Certificate',
                    'description' => 'PSA / NSO Birth Certificate (clear scan or photo)',
                    'visible' => true,
                    'required' => false,
                ],
                'form_138' => [
                    'label' => 'Form 138 / Report Card',
                    'description' => 'Latest official high school or prior school report card',
                    'visible' => true,
                    'required' => false,
                ],
                'good_moral_cert' => [
                    'label' => 'Good Moral Certificate',
                    'description' => 'Certificate of Good Moral Character from previous school',
                    'visible' => true,
                    'required' => false,
                ],
                'picture_1x1' => [
                    'label' => '1x1 ID Photo',
                    'description' => 'Recent clear 1x1 or 2x2 plain-background ID photo',
                    'visible' => true,
                    'required' => false,
                ],
            ];
        }

        return [
            'birth_certificate' => [
                'label' => 'Birth Certificate / Identity Proof',
                'description' => 'Official birth certificate or government-issued identity document (clear scan or photo)',
                'visible' => true,
                'required' => false,
            ],
            'form_138' => [
                'label' => 'Academic Transcript / Report Card',
                'description' => 'Latest official academic transcript or prior school report card',
                'visible' => true,
                'required' => false,
            ],
            'good_moral_cert' => [
                'label' => 'Character / Transfer Reference',
                'description' => 'Certificate of good standing, conduct, or transfer reference from previous school',
                'visible' => true,
                'required' => false,
            ],
            'picture_1x1' => [
                'label' => 'ID Photo',
                'description' => 'Recent clear plain-background identification photo',
                'visible' => true,
                'required' => false,
            ],
        ];
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

    /**
     * Resolve school-level operational profile used to adapt Student & Document workflows.
     *
     * @return array<string, mixed>
     */
    public function schoolProfile(?int $schoolId = null): array
    {
        $resolvedSchoolId = $schoolId ?? $this->schoolId();
        $config = $this->get($resolvedSchoolId);
        $school = $resolvedSchoolId !== null
            ? \App\Models\School::query()->find($resolvedSchoolId)
            : app(TenantContext::class)->getCurrentSchool();

        $schoolLevel = $school?->school_level;
        $levelValue = $schoolLevel instanceof \App\Enums\SchoolLevel
            ? $schoolLevel->value
            : (is_string($schoolLevel) ? $schoolLevel : 'higher_education');

        $enabledTypes = $config['enabled_types'] ?? ['college'];
        $isBasicEd = in_array($levelValue, ['elementary', 'junior_high', 'senior_high'], true)
            || ($enabledTypes === ['shs']);
        $isTvet = $levelValue === 'technical_vocational' || ($enabledTypes === ['tesda']);
        $isHigherEd = ! $isBasicEd && ! $isTvet;
        $isMultiDepartment = count($enabledTypes) > 1;

        $guardianRequiredTypes = $isBasicEd
            ? $enabledTypes
            : array_values(array_intersect($enabledTypes, ['shs']));

        $termLabel = match ($levelValue) {
            'elementary', 'junior_high' => 'Quarter',
            'technical_vocational' => 'Batch / Term',
            default => 'Semester',
        };

        $placementLabel = match (true) {
            $isBasicEd => 'Grade & Strand',
            $isTvet => 'Qualification & Level',
            $isMultiDepartment => 'Program / Strand',
            default => 'Program & Year',
        };

        return [
            'school_id' => $school?->id ?? $resolvedSchoolId,
            'school_name' => $school?->name,
            'country_code' => $school?->country_code,
            'school_level' => $levelValue,
            'school_level_label' => $schoolLevel instanceof \App\Enums\SchoolLevel ? $schoolLevel->getLabel() : 'College / University',
            'preset' => $config['preset'] ?? 'neutral',
            'philippine_reporting' => (bool) ($config['philippine_reporting'] ?? false),
            'is_basic_ed' => $isBasicEd,
            'is_tvet' => $isTvet,
            'is_higher_ed' => $isHigherEd,
            'is_multi_department' => $isMultiDepartment,
            'guardian_required_types' => $guardianRequiredTypes,
            'term_label' => $termLabel,
            'placement_label' => $placementLabel,
        ];
    }

    /**
     * Collect all valid year-level options configured across enabled student types for directory filters.
     *
     * @return list<array{value: string, label: string, numeric: int}>
     */
    public function filterYearLevels(?int $schoolId = null, ?string $studentType = null): array
    {
        $config = $this->get($schoolId);
        $types = filled($studentType)
            ? [$studentType]
            : ($config['enabled_types'] ?? ['college']);

        $byValue = [];
        foreach ($types as $type) {
            foreach ($config['year_levels'][$type] ?? [] as $item) {
                $val = (int) ($item['value'] ?? 0);
                if ($val <= 0) {
                    continue;
                }
                if (! isset($byValue[$val])) {
                    $byValue[$val] = [
                        'value' => (string) $val,
                        'label' => (string) ($item['label'] ?? "Year {$val}"),
                        'numeric' => $val,
                    ];
                }
            }
        }

        ksort($byValue);

        return array_values($byValue);
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
        $defaults = self::defaults();
        $merged = array_replace($defaults, $configuration);
        $merged['external_identifier'] = array_replace(
            $defaults['external_identifier'],
            is_array($configuration['external_identifier'] ?? null) ? $configuration['external_identifier'] : [],
        );
        $merged['signup'] = array_replace(
            $defaults['signup'],
            is_array($configuration['signup'] ?? null) ? $configuration['signup'] : [],
        );
        $storedDocuments = is_array($configuration['documents'] ?? null) ? $configuration['documents'] : [];
        $mergedDocuments = [];
        foreach (self::documentSlotKeys() as $slotKey) {
            $defaultSlot = $defaults['documents'][$slotKey];
            $storedSlot = is_array($storedDocuments[$slotKey] ?? null) ? $storedDocuments[$slotKey] : [];
            $mergedDocuments[$slotKey] = array_replace($defaultSlot, $storedSlot);
        }
        $merged['documents'] = $mergedDocuments;

        return $this->resolved[$scope] = $merged;
    }

    /**
     * Return configured student document vault slots for a school or installation.
     *
     * @return array<string, array{label: string, description: string, visible: bool, required: bool, accepted: string}>
     */
    public function documentChecklist(?int $schoolId = null, bool $onlyVisible = true): array
    {
        $documents = $this->get($schoolId)['documents'];
        $accepted = self::documentSlotAcceptedFormats();
        $checklist = [];

        foreach (self::documentSlotKeys() as $slotKey) {
            $slot = $documents[$slotKey];
            $visible = (bool) ($slot['visible'] ?? true);

            if ($onlyVisible && ! $visible) {
                continue;
            }

            $checklist[$slotKey] = [
                'label' => (string) $slot['label'],
                'description' => (string) ($slot['description'] ?? ''),
                'visible' => $visible,
                'required' => (bool) ($slot['required'] ?? false),
                'accepted' => $accepted[$slotKey] ?? 'PDF, JPG, PNG, or WebP up to 5 MB',
            ];
        }

        return $checklist;
    }

    /**
     * Build dynamic, privacy-safe portal signup presentation metadata for a school or installation.
     *
     * @return array<string, mixed>
     */
    public function signupPresentation(?int $schoolId = null): array
    {
        $config = $this->get($schoolId);
        $external = $config['external_identifier'];
        $signup = $config['signup'];
        $generator = app(IdentifierGenerator::class);

        $studentPolicy = $generator->studentPolicy(schoolId: $schoolId);
        $staffPolicy = $generator->staffPolicy(schoolId: $schoolId);

        $studentIdLabel = mb_trim((string) ($studentPolicy['display_label'] ?? ''));
        if ($studentIdLabel === '') {
            $studentIdLabel = 'Student ID';
        }

        $staffIdLabel = mb_trim((string) ($staffPolicy['display_label'] ?? ''));
        if ($staffIdLabel === '' || mb_strtolower($staffIdLabel) === 'student id') {
            $staffIdLabel = 'Faculty ID';
        }

        $enabledTypes = is_array($config['enabled_types'] ?? null) ? $config['enabled_types'] : ['college'];
        $externalStudentTypes = is_array($external['student_types'] ?? null) ? $external['student_types'] : [];
        $externalAppliesToEnabled = (bool) ($external['enabled'] ?? false)
            && array_intersect($enabledTypes, $externalStudentTypes) !== [];
        $allowExternalForSignup = $externalAppliesToEnabled
            && ((bool) ($external['allow_for_signup'] ?? true) || (bool) ($external['use_as_student_id'] ?? false));
        $externalLabel = mb_trim((string) ($external['label'] ?? 'External Identifier'));
        if ($externalLabel === '') {
            $externalLabel = 'External Identifier';
        }

        $externalOnly = $allowExternalForSignup
            && (bool) ($external['use_as_student_id'] ?? false)
            && count(array_diff($enabledTypes, $externalStudentTypes)) === 0;

        $universalIdentifierLabel = $externalOnly
            ? $externalLabel
            : ($allowExternalForSignup ? "{$studentIdLabel} or {$externalLabel}" : $studentIdLabel);

        $studentPreview = null;
        try {
            $studentPreview = $generator->previewStudentId(schoolId: $schoolId);
        } catch (Throwable) {
            $studentPreview = null;
        }

        $staffPreview = null;
        try {
            $staffPreview = $generator->previewStaffId($schoolId);
        } catch (Throwable) {
            $staffPreview = null;
        }

        $externalPlaceholder = filled($external['placeholder'] ?? null)
            ? mb_trim((string) $external['placeholder'])
            : null;

        $configuredStudentPlaceholder = filled($signup['student_identifier_placeholder'] ?? null)
            ? mb_trim((string) $signup['student_identifier_placeholder'])
            : null;

        if ($configuredStudentPlaceholder !== null) {
            $studentPlaceholder = $configuredStudentPlaceholder;
        } elseif ($externalOnly) {
            $studentPlaceholder = $externalPlaceholder ?? "Enter your {$externalLabel}";
        } elseif ($allowExternalForSignup) {
            $primaryExample = filled($studentPreview) ? $studentPreview : '2026-0001';
            $secondaryExample = $externalPlaceholder !== null
                ? preg_replace('/^e\.g\.\s*/i', '', $externalPlaceholder)
                : null;
            $studentPlaceholder = filled($secondaryExample)
                ? "e.g. {$primaryExample} or {$secondaryExample}"
                : "e.g. {$primaryExample}";
        } else {
            $studentPlaceholder = filled($studentPreview)
                ? "e.g. {$studentPreview}"
                : "Enter your {$studentIdLabel}";
        }

        $configuredStudentHelp = filled($signup['student_identifier_help_text'] ?? null)
            ? mb_trim((string) $signup['student_identifier_help_text'])
            : null;

        if ($configuredStudentHelp !== null) {
            $studentHelpText = $configuredStudentHelp;
        } elseif ($externalOnly && filled($external['help_text'] ?? null)) {
            $studentHelpText = (string) $external['help_text'];
        } elseif ($allowExternalForSignup) {
            $studentHelpText = "Enter your {$studentIdLabel} or {$externalLabel} — we'll automatically match your student record.";
        } else {
            $studentHelpText = 'Found on your admission notice, registration slip, or campus ID card.';
        }

        $configuredFacultyPlaceholder = filled($signup['faculty_identifier_placeholder'] ?? null)
            ? mb_trim((string) $signup['faculty_identifier_placeholder'])
            : null;

        $facultyPlaceholder = $configuredFacultyPlaceholder
            ?? (filled($staffPreview) ? "e.g. {$staffPreview}" : "Enter your {$staffIdLabel}");

        $facultyHelpText = filled($signup['faculty_identifier_help_text'] ?? null)
            ? mb_trim((string) $signup['faculty_identifier_help_text'])
            : "Enter the {$staffIdLabel} assigned by HR or your institution.";

        $enabledTrackLabels = collect($enabledTypes)
            ->map(fn (string $type): string => (string) ($config['type_labels'][$type] ?? ucfirst($type)))
            ->filter(fn (string $label): bool => mb_trim($label) !== '')
            ->values()
            ->all();

        return [
            'student_id_label' => $studentIdLabel,
            'faculty_id_label' => $staffIdLabel,
            'external_identifier_enabled' => $allowExternalForSignup,
            'external_identifier_label' => $externalLabel,
            'external_identifier_help_text' => filled($external['help_text'] ?? null) ? (string) $external['help_text'] : null,
            'external_identifier_only' => $externalOnly,
            'universal_identifier_label' => $universalIdentifierLabel,
            'student_identifier_placeholder' => $studentPlaceholder,
            'student_identifier_help_text' => $studentHelpText,
            'faculty_identifier_placeholder' => $facultyPlaceholder,
            'faculty_identifier_help_text' => $facultyHelpText,
            'enabled_track_labels' => $enabledTrackLabels,
            'allow_student_self_signup' => (bool) ($signup['allow_student_self_signup'] ?? true),
            'allow_faculty_self_signup' => (bool) ($signup['allow_faculty_self_signup'] ?? true),
            'allow_identifier_recovery' => (bool) ($signup['allow_identifier_recovery'] ?? true),
            'require_faculty_id' => (bool) ($signup['require_faculty_id'] ?? false),
            'require_password_on_social_signup' => (bool) ($signup['require_password_on_social_signup'] ?? false),
        ];
    }

    public function update(array $configuration, ?int $schoolId = null): void
    {
        $schoolId ??= $this->schoolId();
        DB::table('student_configurations')->upsert([
            ['scope' => self::scope($schoolId), 'school_id' => $schoolId, 'configuration' => json_encode($configuration, JSON_THROW_ON_ERROR), 'updated_at' => now(), 'created_at' => now()],
        ], ['scope'], ['configuration', 'updated_at']);
        unset($this->resolved[self::scope($schoolId)]);
    }

    /** Recheck stable field identities after taking the settings write lock. */
    public function validateCustomFieldChanges(array $fields): void
    {
        $existing = collect($this->get()['custom_fields'])->keyBy('key');
        $submitted = collect($fields)->keyBy('key');
        if ($existing->keys()->diff($submitted->keys())->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['custom_fields' => 'Settings changed while you were editing. Reload and archive fields instead of removing them.']);
        }
        foreach ($fields as $index => $field) {
            if (isset($existing[$field['key']]) && $existing[$field['key']]['type'] !== $field['type']) {
                throw \Illuminate\Validation\ValidationException::withMessages(["custom_fields.{$index}.type" => 'The type of an existing field cannot be changed; archive it and create another field.']);
            }
        }
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
        foreach ($this->get($schoolId)['custom_fields'] as $field) {
            if ($field['active'] && $field['required']) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'custom_fields.'.$field['key'] => $field['label'].' is required by this school. Create this student using the administrator form.',
                ]);
            }
        }
        foreach ($this->get($schoolId)['fields'] as $field => $policy) {
            if ($policy['visible'] && $policy['required'] && ! in_array($field, $supportedFields, true)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    $field => $policy['label'].' is required by this school. Create this student using the administrator form.',
                ]);
            }
        }
    }

    /** @return array<string, mixed> */
    public function customFieldRules(?Student $student = null, ?int $schoolId = null): array
    {
        $rules = ['custom_fields' => ['sometimes', 'array']];
        foreach ($this->get($student?->school_id ?? $schoolId)['custom_fields'] as $field) {
            if (! $field['active']) {
                continue;
            }
            $existingValue = $student === null ? null : data_get($student->profile_details, 'custom_fields.'.$field['key']);
            $presence = $field['required'] && ($student === null || filled($existingValue)) ? 'required' : 'sometimes';
            $valueRules = $presence === 'required' ? ['required'] : ['sometimes', 'nullable'];
            $rules['custom_fields.'.$field['key']] = [
                ...$valueRules,
                ...match ($field['type']) {
                    'number' => ['numeric', function (string $attribute, mixed $value, Closure $fail): void {
                        if (! is_scalar($value) || mb_strlen((string) $value) > 100) {
                            $fail('The number must be at most 100 characters.');
                        }
                    }],
                    'date' => ['date_format:Y-m-d'],
                    'choice' => ['string', Rule::in($student !== null && is_string($existingValue) ? array_unique([...$field['options'], $existingValue]) : $field['options'])],
                    default => ['string', 'max:1000'],
                },
            ];
        }

        return $rules;
    }

    /** Preserve values for archived fields and fields absent from partial edits. */
    public function mergeCustomValues(Student $student, array $validated, ?int $schoolId = null): void
    {
        if (! array_key_exists('custom_fields', $validated)) {
            return;
        }
        $details = is_array($student->profile_details) ? $student->profile_details : [];
        $current = is_array($details['custom_fields'] ?? null) ? $details['custom_fields'] : [];
        $submitted = $validated['custom_fields'] ?? [];
        if (! is_array($submitted)) {
            return;
        }
        foreach ($this->get($student->school_id ?? $schoolId)['custom_fields'] as $field) {
            if ($field['active'] && array_key_exists($field['key'], $submitted)) {
                $current[$field['key']] = $submitted[$field['key']];
            }
        }
        $details['custom_fields'] = $current;
        $student->profile_details = $details;
    }

    /** Only expose active, school-configured fields to authorized staff views. */
    public function displayCustomValues(Student $student): array
    {
        $values = data_get($student->profile_details, 'custom_fields', []);

        return collect($this->get($student->school_id)['custom_fields'])
            ->filter(fn (array $field): bool => $field['active'])
            ->map(fn (array $field): array => ['key' => $field['key'], 'label' => $field['label'], 'value' => is_array($values) ? ($values[$field['key']] ?? null) : null])
            ->values()->all();
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
            ...match ($external['format']) {
                'digits' => ['regex:/\A[0-9]+\z/'],
                'alphanumeric' => ['regex:/\A[A-Za-z0-9]+\z/'],
                default => [],
            },
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

    /**
     * @return array<string, string>
     */
    private static function documentSlotAcceptedFormats(): array
    {
        return [
            'birth_certificate' => 'PDF, JPG, PNG, or WebP up to 5 MB',
            'form_138' => 'PDF, JPG, PNG, or WebP up to 5 MB',
            'good_moral_cert' => 'PDF, JPG, PNG, or WebP up to 5 MB',
            'picture_1x1' => 'JPG, PNG, WebP, or PDF up to 5 MB',
        ];
    }
}
