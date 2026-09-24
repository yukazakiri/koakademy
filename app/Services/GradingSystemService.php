<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\GradingPolicy;
use App\Models\GradingPolicyVersion;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Centralized access to the grading system configuration.
 *
 * Stored inside `general_settings.more_configs.grading`. Also resolves the
 * list of courses + subjects used by the exclusion picker and by any backend
 * routine that wants to know whether a subject is excluded from GWA.
 */
final class GradingSystemService
{
    public const string CONFIG_KEY = 'grading';

    /**
     * Default grading configuration. Overridable by admins via the settings UI.
     *
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'name' => 'Default grading policy',
            'input_type' => 'numeric',
            'numeric_min' => 0,
            'numeric_max' => 100,
            'direction' => 'higher_is_better',
            'decimal_places' => 2,
            'include_failed_in_gwa' => true,
            'gwa_formula' => 'weighted_units',
            'gwa_subject_divisor_basis' => 'enrolled_subjects',
            'gwa_calculation_metric' => 'numeric_grade',
            'retake_strategy' => 'latest',
            'include_credited_in_gwa' => true,
            'zero_is_dropped' => false,
            'treat_incomplete_as' => 'exclude',
            'exclude_zero_unit_subjects' => true,
            'transferee_scale_enabled' => true,
            'transferee_point_scale_min' => 1.0,
            'transferee_point_scale_max' => 5.0,
            'transferee_point_passing_grade' => 3.0,
            'transferee_point_direction' => 'lower_is_better',
            'transferee_conversion_method' => 'formula',
            'excluded_keywords' => [],
            'excluded_subject_ids' => [],
            'bands' => [
                ['id' => 'pass', 'label' => 'Passing', 'min' => 75, 'max' => 100, 'symbol' => null, 'outcome' => 'pass', 'quality_points' => null, 'color' => 'success', 'sort_order' => 0],
                ['id' => 'fail', 'label' => 'Failing', 'min' => 0, 'max' => 74.9999, 'symbol' => null, 'outcome' => 'fail', 'quality_points' => null, 'color' => 'destructive', 'sort_order' => 1],
            ],
            'components' => [
                ['id' => 'prelim', 'key' => 'prelim', 'label' => 'Prelim', 'weight' => 30, 'required' => true, 'sort_order' => 0],
                ['id' => 'midterm', 'key' => 'midterm', 'label' => 'Midterm', 'weight' => 30, 'required' => true, 'sort_order' => 1],
                ['id' => 'final', 'key' => 'final', 'label' => 'Final', 'weight' => 40, 'required' => true, 'sort_order' => 2],
            ],
        ];
    }

    /**
     * Merge stored config with defaults and normalize types.
     *
     * @return array<string, mixed>
     */
    public function getConfig(?School $school = null): array
    {
        $school ??= app(TenantContext::class)->getCurrentSchool();

        if ($school instanceof School) {
            return $this->activeConfigurationForSchool($school, createWhenMissing: false);
        }

        return $this->legacyConfiguration();
    }

    /** @return array<string, mixed> */
    public function ensureConfig(?School $school = null): array
    {
        $school ??= app(TenantContext::class)->getCurrentSchool();

        return $school instanceof School
            ? $this->activeConfigurationForSchool($school)
            : $this->legacyConfiguration();
    }

    /** @return array<string, mixed> */
    public function legacyConfiguration(): array
    {
        $settings = app(GeneralSettingsService::class)->getGlobalSettingsModel();
        $stored = [];

        if ($settings && is_array($settings->more_configs ?? null)) {
            $stored = $settings->more_configs[self::CONFIG_KEY] ?? [];
        }

        $legacy = is_array($stored) ? $stored : [];
        $config = self::defaults();

        if (array_key_exists('point_passing_grade', $legacy) || array_key_exists('percent_passing_grade', $legacy)) {
            $scale = $legacy['scale'] ?? 'percent';
            $isPoint = $scale === 'point';
            $threshold = (float) ($isPoint ? ($legacy['point_passing_grade'] ?? 3) : ($legacy['percent_passing_grade'] ?? 75));
            $config = [
                ...$config,
                'input_type' => 'numeric',
                'numeric_min' => $isPoint ? 1 : 0,
                'numeric_max' => $isPoint ? 5 : 100,
                'direction' => $isPoint ? 'lower_is_better' : 'higher_is_better',
                'decimal_places' => (int) ($isPoint ? ($legacy['point_decimal_places'] ?? 4) : ($legacy['percent_decimal_places'] ?? 2)),
                'bands' => $isPoint
                    ? [
                        ['id' => 'pass', 'label' => 'Passing', 'min' => 1, 'max' => $threshold, 'symbol' => null, 'outcome' => 'pass', 'quality_points' => null, 'color' => 'success', 'sort_order' => 0],
                        ['id' => 'fail', 'label' => 'Failing', 'min' => $threshold + 0.0001, 'max' => 5, 'symbol' => null, 'outcome' => 'fail', 'quality_points' => null, 'color' => 'destructive', 'sort_order' => 1],
                    ]
                    : [
                        ['id' => 'pass', 'label' => 'Passing', 'min' => $threshold, 'max' => 100, 'symbol' => null, 'outcome' => 'pass', 'quality_points' => null, 'color' => 'success', 'sort_order' => 0],
                        ['id' => 'fail', 'label' => 'Failing', 'min' => 0, 'max' => $threshold - 0.0001, 'symbol' => null, 'outcome' => 'fail', 'quality_points' => null, 'color' => 'destructive', 'sort_order' => 1],
                    ],
                'include_failed_in_gwa' => (bool) ($legacy['include_failed_in_gwa'] ?? true),
                'excluded_keywords' => $legacy['excluded_keywords'] ?? [],
                'excluded_subject_ids' => $legacy['excluded_subject_ids'] ?? [],
            ];
        }

        return $this->normalize($config);
    }

    /**
     * Determine whether a numeric grade meets the configured passing threshold.
     *
     * @param  array<string, mixed>|null  $config
     */
    public function isPassingGrade(float|int|string|null $grade, ?array $config = null): bool
    {
        if ($grade === null || $grade === '' || ! is_numeric($grade)) {
            return false;
        }

        $config = $config === null ? $this->getConfig() : $this->normalize($config);
        $evaluation = app(GradeEvaluationService::class)->evaluate((float) $grade, $config);

        return $evaluation['outcome'] === 'pass';
    }

    /**
     * Persist grading configuration.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input, ?School $school = null, ?User $author = null): array
    {
        $school ??= app(TenantContext::class)->getCurrentSchool();

        if ($school instanceof School) {
            return $this->publishForSchool($school, $input, $author);
        }

        $settings = GeneralSetting::query()->first();

        if (! $settings) {
            $settings = GeneralSetting::query()->create([
                'site_name' => config('app.name', 'KoAkademy'),
            ]);
        }

        $moreConfigs = is_array($settings->more_configs ?? null) ? $settings->more_configs : [];
        $normalized = $this->normalize($input);
        $moreConfigs[self::CONFIG_KEY] = $normalized;

        $settings->update(['more_configs' => $moreConfigs]);

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public function publishForSchool(School $school, array $configuration, ?User $author = null): array
    {
        $normalized = $this->normalize($configuration);

        return DB::transaction(function () use ($school, $normalized, $author): array {
            $policy = GradingPolicy::query()->firstOrCreate(
                ['school_id' => $school->id],
                ['name' => $normalized['name'], 'created_by' => $author?->id],
            );

            $policy->update(['name' => $normalized['name']]);
            $nextVersion = ((int) $policy->versions()->max('version')) + 1;
            $version = $policy->versions()->create([
                'version' => $nextVersion,
                'state' => GradingPolicyVersion::Published,
                'configuration' => $normalized,
                'created_by' => $author?->id,
                'published_by' => $author?->id,
                'published_at' => now(),
            ]);

            $policy->update(['active_version_id' => $version->id]);

            return [...$normalized, 'policy_version_id' => $version->id, 'policy_version' => $version->version];
        });
    }

    /** @return array<string, mixed> */
    public function activeConfigurationForSchool(School $school, bool $createWhenMissing = true): array
    {
        $policy = GradingPolicy::query()->with('activeVersion')->where('school_id', $school->id)->first();

        if ($policy?->activeVersion instanceof GradingPolicyVersion) {
            return [
                ...$this->normalize($policy->activeVersion->configuration),
                'policy_version_id' => $policy->activeVersion->id,
                'policy_version' => $policy->activeVersion->version,
            ];
        }

        if ($createWhenMissing) {
            return $this->publishForSchool($school, $this->legacyConfiguration());
        }

        return $this->legacyConfiguration();
    }

    public function activeVersionForSchool(?School $school = null): ?GradingPolicyVersion
    {
        $school ??= app(TenantContext::class)->getCurrentSchool();

        if (! $school instanceof School) {
            return null;
        }

        $this->activeConfigurationForSchool($school);

        return GradingPolicy::query()->with('activeVersion')->where('school_id', $school->id)->first()?->activeVersion;
    }

    /**
     * Courses with their subjects, shaped for the exclusion picker UI.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCoursesWithSubjects(): array
    {
        return Course::query()
            ->with(['subjects' => function ($query): void {
                $query->orderBy('academic_year')->orderBy('semester')->orderBy('code');
            }])
            ->orderBy('code')
            ->get()
            ->map(fn (Course $course): array => [
                'id' => (int) $course->id,
                'code' => (string) $course->code,
                'title' => (string) $course->title,
                'subjects' => $course->subjects->map(fn ($subject): array => [
                    'id' => (int) $subject->id,
                    'code' => (string) ($subject->code ?? ''),
                    'title' => (string) ($subject->title ?? ''),
                    'units' => (int) ($subject->units ?? 0),
                    'year_level' => (int) ($subject->academic_year ?? 0),
                    'semester' => (int) ($subject->semester ?? 0),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function normalize(array $config): array
    {
        $defaults = self::defaults();
        $inputType = in_array($config['input_type'] ?? null, ['numeric', 'symbol'], true) ? $config['input_type'] : $defaults['input_type'];
        $direction = in_array($config['direction'] ?? null, ['higher_is_better', 'lower_is_better'], true)
            ? $config['direction']
            : $defaults['direction'];
        $numericMin = is_numeric($config['numeric_min'] ?? null) ? max(0.0, min(999.99, (float) $config['numeric_min'])) : (float) $defaults['numeric_min'];
        $numericMax = is_numeric($config['numeric_max'] ?? null) ? max(0.0, min(999.99, (float) $config['numeric_max'])) : (float) $defaults['numeric_max'];

        $keywords = array_values(array_filter(array_map(
            fn ($k): string => mb_trim((string) $k),
            is_array($config['excluded_keywords'] ?? null) ? $config['excluded_keywords'] : []
        ), fn (string $k): bool => $k !== ''));

        $subjectIds = array_values(array_unique(array_map(
            fn (float|int|string $id): int => (int) $id,
            array_filter(
                is_array($config['excluded_subject_ids'] ?? null) ? $config['excluded_subject_ids'] : [],
                fn ($id): bool => is_numeric($id) && (int) $id > 0
            )
        )));

        $bands = collect($config['bands'] ?? $defaults['bands'])
            ->filter(fn ($band): bool => is_array($band))
            ->map(fn (array $band, int $index): array => [
                'id' => mb_trim((string) ($band['id'] ?? "band_{$index}")),
                'symbol' => ($symbol = mb_trim((string) ($band['symbol'] ?? ''))) === '' ? null : $symbol,
                'label' => mb_trim((string) ($band['label'] ?? 'Band '.($index + 1))),
                'min' => is_numeric($band['min'] ?? null) ? max(0.0, min(999.99, round((float) $band['min'], 2))) : null,
                'max' => is_numeric($band['max'] ?? null) ? max(0.0, min(999.99, round((float) $band['max'], 2))) : null,
                'outcome' => in_array($band['outcome'] ?? null, ['pass', 'fail', 'incomplete', 'withdrawn', 'non_credit'], true) ? $band['outcome'] : 'incomplete',
                'quality_points' => is_numeric($band['quality_points'] ?? null) ? (float) $band['quality_points'] : null,
                'color' => mb_trim((string) ($band['color'] ?? 'muted')),
                'sort_order' => (int) ($band['sort_order'] ?? $index),
            ])
            ->sortBy('sort_order')
            ->values()
            ->all();

        $components = collect($config['components'] ?? $defaults['components'])
            ->filter(fn ($component): bool => is_array($component))
            ->map(fn (array $component, int $index): array => [
                'id' => mb_trim((string) ($component['id'] ?? $component['key'] ?? "component_{$index}")),
                'key' => mb_trim((string) ($component['key'] ?? "component_{$index}")),
                'label' => mb_trim((string) ($component['label'] ?? 'Component '.($index + 1))),
                'weight' => round((float) ($component['weight'] ?? 0), 4),
                'required' => (bool) ($component['required'] ?? true),
                'sort_order' => (int) ($component['sort_order'] ?? $index),
            ])
            ->sortBy('sort_order')
            ->values()
            ->all();

        $gwaFormula = in_array($config['gwa_formula'] ?? null, ['weighted_units', 'weighted_subjects', 'unweighted'], true)
            ? $config['gwa_formula']
            : $defaults['gwa_formula'];
        $gwaSubjectDivisorBasis = in_array($config['gwa_subject_divisor_basis'] ?? null, ['enrolled_subjects', 'graded_subjects', 'curriculum_subjects'], true)
            ? $config['gwa_subject_divisor_basis']
            : $defaults['gwa_subject_divisor_basis'];
        $gwaCalculationMetric = in_array($config['gwa_calculation_metric'] ?? null, ['numeric_grade', 'quality_points'], true)
            ? $config['gwa_calculation_metric']
            : $defaults['gwa_calculation_metric'];
        $retakeStrategy = in_array($config['retake_strategy'] ?? null, ['latest', 'highest', 'first', 'all'], true)
            ? $config['retake_strategy']
            : $defaults['retake_strategy'];
        $includeCreditedInGwa = (bool) ($config['include_credited_in_gwa'] ?? $defaults['include_credited_in_gwa']);
        $zeroIsDropped = (bool) ($config['zero_is_dropped'] ?? $defaults['zero_is_dropped']);
        $treatIncompleteAs = in_array($config['treat_incomplete_as'] ?? null, ['exclude', 'fail'], true)
            ? $config['treat_incomplete_as']
            : $defaults['treat_incomplete_as'];
        $excludeZeroUnitSubjects = (bool) ($config['exclude_zero_unit_subjects'] ?? $defaults['exclude_zero_unit_subjects']);
        $transfereeScaleEnabled = (bool) ($config['transferee_scale_enabled'] ?? $defaults['transferee_scale_enabled']);
        $transfereePointScaleMin = is_numeric($config['transferee_point_scale_min'] ?? null)
            ? max(0.0, min(100.0, (float) $config['transferee_point_scale_min']))
            : (float) $defaults['transferee_point_scale_min'];
        $transfereePointScaleMax = is_numeric($config['transferee_point_scale_max'] ?? null)
            ? max(0.0, min(100.0, (float) $config['transferee_point_scale_max']))
            : (float) $defaults['transferee_point_scale_max'];
        $transfereePointPassingGrade = is_numeric($config['transferee_point_passing_grade'] ?? null)
            ? max(0.0, min(100.0, (float) $config['transferee_point_passing_grade']))
            : (float) $defaults['transferee_point_passing_grade'];
        $transfereePointDirection = in_array($config['transferee_point_direction'] ?? null, ['lower_is_better', 'higher_is_better'], true)
            ? $config['transferee_point_direction']
            : $defaults['transferee_point_direction'];
        $transfereeConversionMethod = in_array($config['transferee_conversion_method'] ?? null, ['formula', 'table'], true)
            ? $config['transferee_conversion_method']
            : $defaults['transferee_conversion_method'];

        return [
            'name' => mb_trim((string) ($config['name'] ?? $defaults['name'])) ?: $defaults['name'],
            'input_type' => $inputType,
            'numeric_min' => min($numericMin, $numericMax),
            'numeric_max' => max($numericMin, $numericMax),
            'direction' => $direction,
            'decimal_places' => max(0, min(2, (int) ($config['decimal_places'] ?? $defaults['decimal_places']))),
            'include_failed_in_gwa' => (bool) ($config['include_failed_in_gwa'] ?? true),
            'gwa_formula' => $gwaFormula,
            'gwa_subject_divisor_basis' => $gwaSubjectDivisorBasis,
            'gwa_calculation_metric' => $gwaCalculationMetric,
            'retake_strategy' => $retakeStrategy,
            'include_credited_in_gwa' => $includeCreditedInGwa,
            'zero_is_dropped' => $zeroIsDropped,
            'treat_incomplete_as' => $treatIncompleteAs,
            'exclude_zero_unit_subjects' => $excludeZeroUnitSubjects,
            'transferee_scale_enabled' => $transfereeScaleEnabled,
            'transferee_point_scale_min' => min($transfereePointScaleMin, $transfereePointScaleMax),
            'transferee_point_scale_max' => max($transfereePointScaleMin, $transfereePointScaleMax),
            'transferee_point_passing_grade' => $transfereePointPassingGrade,
            'transferee_point_direction' => $transfereePointDirection,
            'transferee_conversion_method' => $transfereeConversionMethod,
            'excluded_keywords' => $keywords,
            'excluded_subject_ids' => $subjectIds,
            'bands' => $bands,
            'components' => $components,
        ];
    }
}
