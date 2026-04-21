<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Course;
use App\Models\GeneralSetting;

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
            'scale' => 'auto', // 'point' | 'percent' | 'auto'
            'point_passing_grade' => 3.0,
            'percent_passing_grade' => 75,
            'point_decimal_places' => 4,
            'percent_decimal_places' => 2,
            'include_failed_in_gwa' => true,
            'excluded_keywords' => ['NSTP', 'OJT'],
            'excluded_subject_ids' => [],
        ];
    }

    /**
     * Merge stored config with defaults and normalize types.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        $settings = GeneralSetting::first();
        $stored = [];

        if ($settings && is_array($settings->more_configs ?? null)) {
            $stored = $settings->more_configs[self::CONFIG_KEY] ?? [];
        }

        $config = array_merge(self::defaults(), is_array($stored) ? $stored : []);

        return $this->normalize($config);
    }

    /**
     * Persist grading configuration.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(array $input): array
    {
        $settings = GeneralSetting::first();

        if (! $settings) {
            $settings = GeneralSetting::query()->create([
                'site_name' => config('app.name', 'KoAkademy'),
            ]);
        }

        $moreConfigs = is_array($settings->more_configs ?? null) ? $settings->more_configs : [];
        $normalized = $this->normalize(array_merge(self::defaults(), $input));
        $moreConfigs[self::CONFIG_KEY] = $normalized;

        $settings->update(['more_configs' => $moreConfigs]);

        return $normalized;
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
        $scale = is_string($config['scale'] ?? null) ? $config['scale'] : 'auto';
        if (! in_array($scale, ['point', 'percent', 'auto'], true)) {
            $scale = 'auto';
        }

        $pointPassing = (float) ($config['point_passing_grade'] ?? 3.0);
        $percentPassing = (float) ($config['percent_passing_grade'] ?? 75);

        $keywords = array_values(array_filter(array_map(
            fn ($k): string => mb_trim((string) $k),
            is_array($config['excluded_keywords'] ?? null) ? $config['excluded_keywords'] : []
        ), fn (string $k): bool => $k !== ''));

        $subjectIds = array_values(array_unique(array_map(
            fn ($id): int => (int) $id,
            array_filter(
                is_array($config['excluded_subject_ids'] ?? null) ? $config['excluded_subject_ids'] : [],
                fn ($id): bool => is_numeric($id) && (int) $id > 0
            )
        )));

        return [
            'scale' => $scale,
            'point_passing_grade' => max(1.0, min(5.0, $pointPassing)),
            'percent_passing_grade' => max(0.0, min(100.0, $percentPassing)),
            'point_decimal_places' => (int) max(0, min(6, (int) ($config['point_decimal_places'] ?? 4))),
            'percent_decimal_places' => (int) max(0, min(6, (int) ($config['percent_decimal_places'] ?? 2))),
            'include_failed_in_gwa' => (bool) ($config['include_failed_in_gwa'] ?? true),
            'excluded_keywords' => $keywords,
            'excluded_subject_ids' => $subjectIds,
        ];
    }
}
