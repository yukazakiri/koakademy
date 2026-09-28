<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Enums\StudentStatus;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentTuition;
use App\Services\GeneralSettingsService;
use App\Services\RegistrarAnalyticsService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;
use Throwable;

/**
 * Live institutional analytics for the executive copilot.
 *
 * Every figure returned here is computed from the database. Where a metric
 * cannot be derived from recorded data the tool reports `null` and says so in
 * `unavailable`, rather than returning a plausible-looking placeholder, so the
 * agent never presents an invented number to an administrator.
 */
final class QueryCampusAnalyticsTool implements Tool
{
    /**
     * Resolved lazily so the tool stays constructible with `new`, which is how
     * the agent registries in app/Ai/Agents and app/Http/Controllers build it.
     */
    private ?GeneralSettingsService $settingsService = null;

    public function description(): Stringable|string
    {
        return 'Query live institutional analytics across enrollment populations, demographic distributions, student retention, graduation clearances, and tuition revenue. Every figure is computed from recorded data; unavailable metrics are reported as null with a reason. Use this for counts, rates, and trends, and SearchStudentsTool when the user needs actual student records or contact details.';
    }

    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'category' => 'required|string|in:overview,enrollment,demographics,clearance,finance,faculty',
        ]);

        $period = $this->currentPeriod();

        return match ($validated['category']) {
            'overview' => $this->json($this->overview($period)),
            'enrollment' => $this->json($this->enrollment($period)),
            'demographics' => $this->json($this->demographics($period)),
            'clearance' => $this->json($this->clearance($period)),
            'finance' => $this->json($this->finance($period)),
            'faculty' => $this->json($this->faculty($period)),
            default => $this->json(['error' => true, 'message' => 'Category not recognized.']),
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()
                ->enum(['overview', 'enrollment', 'demographics', 'clearance', 'finance', 'faculty'])
                ->required()
                ->description('overview = campus headline counts. enrollment = headcount and growth by program, year level, and status. demographics = gender, scholarship, origin, and equity distributions. clearance = clearance completion for the term. finance = assessed tuition, collections, and outstanding balances. faculty = teaching load and department coverage.'),
        ];
    }

    private function settings(): GeneralSettingsService
    {
        return $this->settingsService ??= app(GeneralSettingsService::class);
    }

    /**
     * @return array{school_year: string, semester: int, label: string}
     */
    private function currentPeriod(): array
    {
        $schoolYear = $this->settings()->getCurrentSchoolYearString();
        $semester = $this->settings()->getCurrentSemester();

        return [
            'school_year' => $schoolYear,
            'semester' => $semester,
            'label' => sprintf('%s · Semester %d', $schoolYear, $semester),
        ];
    }

    /**
     * @param  array{school_year: string, semester: int, label: string}  $period
     * @return array<string, mixed>
     */
    private function overview(array $period): array
    {
        $totalStudents = Student::query()->count();
        $enrolledStudents = Student::query()->where('status', StudentStatus::Enrolled->value)->count();
        $activeFaculty = Faculty::query()->count();
        $activeClasses = Classes::query()
            ->whereIn('school_year', [$period['school_year'], str_replace(' ', '', $period['school_year'])])
            ->where('semester', $period['semester'])
            ->count();
        $pendingClearances = StudentClearance::query()->where('is_cleared', false)->count();

        return [
            'academic_period' => $period,
            'headline_metrics' => [
                'total_student_population' => $totalStudents,
                'currently_enrolled' => $enrolledStudents,
                'applicants' => Student::query()->where('status', StudentStatus::Applicant->value)->count(),
                'graduates' => Student::query()->where('status', StudentStatus::Graduated->value)->count(),
                'attrition_current_status' => Student::query()
                    ->whereIn('status', [StudentStatus::Dropped->value, StudentStatus::Withdrawn->value])
                    ->count(),
                'faculty_count' => $activeFaculty,
                'classes_running_this_term' => $activeClasses,
                'pending_clearances' => $pendingClearances,
            ],
            'enrolled_share_of_population_percent' => $totalStudents > 0
                ? round(($enrolledStudents / $totalStudents) * 100, 1)
                : null,
            'unavailable' => [
                'retention_rate_percent' => 'Retention is not a stored figure. Derive it from the enrollment and demographics categories by comparing consecutive terms before quoting a rate.',
            ],
        ];
    }

    /**
     * @param  array{school_year: string, semester: int, label: string}  $period
     * @return array<string, mixed>
     */
    private function enrollment(array $period): array
    {
        try {
            // Pass the period explicitly so these counts describe the same term
            // the rest of the tool reports, rather than whatever the registrar
            // service resolves on its own.
            $analytics = app(RegistrarAnalyticsService::class)->build([
                'school_year' => $period['school_year'],
                'semester' => $period['semester'],
            ])['analytics'];
        } catch (Throwable $e) {
            return [
                'academic_period' => $period,
                'error' => true,
                'message' => "Registrar analytics could not be computed: {$e->getMessage()}",
            ];
        }

        $current = (int) ($analytics['current_semester_count'] ?? 0);
        $previous = (int) ($analytics['previous_semester_count'] ?? 0);
        $schoolYear = (int) ($analytics['current_school_year_count'] ?? 0);

        return [
            'academic_period' => $period,
            'current_term_enrollments' => $current,
            'school_year_enrollments' => $schoolYear,
            'previous_term_enrollments' => $previous,
            'term_over_term_growth_percent' => $previous > 0
                ? round((($current - $previous) / $previous) * 100, 1)
                : null,
            'by_program' => $this->labelCounts($analytics['by_program'] ?? [], 'program', 'title'),
            'by_department' => $this->labelCounts($analytics['by_department'] ?? [], 'department'),
            'by_year_level' => $this->labelCounts($analytics['by_year_level'] ?? [], 'year_level', null, 'Year '),
            'by_student_type' => $this->labelCounts($analytics['by_student_type'] ?? [], 'student_type', null, '', true),
            'by_status' => $this->labelCounts($analytics['by_status'] ?? [], 'status'),
        ];
    }

    /**
     * @param  array{school_year: string, semester: int, label: string}  $period
     * @return array<string, mixed>
     */
    private function demographics(array $period): array
    {
        $enrolled = Student::query()->where('status', StudentStatus::Enrolled->value);

        return [
            'academic_period' => $period,
            'note' => 'Distributions below are computed over currently enrolled students only.',
            'by_gender' => $this->enrolledDistribution($enrolled->clone(), 'gender', ['male', 'female', 'other', 'prefer_not_to_say']),
            'by_year_level' => $this->enrolledDistribution($enrolled->clone(), 'academic_year'),
            'by_scholarship' => $this->enrolledDistribution($enrolled->clone(), 'scholarship_type', ['none']),
            'by_region_of_origin' => $this->enrolledDistribution($enrolled->clone(), 'region_of_origin', [], 15),
            'by_student_type' => $this->enrolledDistribution($enrolled->clone(), 'student_type'),
            'equity_groups' => [
                'indigenous_person' => $enrolled->clone()->where('is_indigenous_person', true)->count(),
                'person_with_disability' => $enrolled->clone()->where('is_pwd', true)->count(),
                'solo_parent' => $enrolled->clone()->where('is_solo_parent', true)->count(),
                'underprivileged' => $enrolled->clone()->where('is_underprivileged', true)->count(),
                'first_generation' => $enrolled->clone()->where('is_first_generation', true)->count(),
            ],
        ];
    }

    /**
     * @param  array{school_year: string, semester: int, label: string}  $period
     * @return array<string, mixed>
     */
    private function clearance(array $period): array
    {
        $current = StudentClearance::query()
            ->whereIn('academic_year', [$period['school_year'], str_replace(' ', '', $period['school_year'])])
            ->where('semester', $period['semester']);

        $cleared = (clone $current)->where('is_cleared', true)->count();
        $pending = (clone $current)->where('is_cleared', false)->count();
        $total = $cleared + $pending;

        $reasons = (clone $current)
            ->where('is_cleared', false)
            ->whereNotNull('remarks')
            ->whereRaw("TRIM(COALESCE(remarks, '')) <> ''")
            ->select('remarks')
            ->get()
            ->map(static fn ($record): array => array_filter(array_map(
                'trim',
                preg_split('/[;,\n]+/', (string) $record->remarks) ?: [],
            )))
            ->flatten()
            ->map(static fn (string $reason): string => mb_trim($reason))
            ->filter(static fn (string $reason): bool => $reason !== '')
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(static fn (int $count, string $reason): array => ['reason' => $reason, 'count' => $count])
            ->values()
            ->all();

        return [
            'academic_period' => $period,
            'clearances_recorded_this_term' => $total,
            'cleared_count' => $cleared,
            'pending_count' => $pending,
            'completion_rate_percent' => $total > 0 ? round(($cleared / $total) * 100, 1) : null,
            'top_pending_reasons' => $reasons,
            'pending_reason_source' => 'Free-text remarks on uncleared clearance records for this term, split on ; , and newlines.',
            'unavailable' => $reasons === []
                ? ['top_pending_reasons' => 'No remarks are recorded on uncleared clearance records for this term, so no reason can be ranked.']
                : [],
        ];
    }

    /**
     * @param  array{school_year: string, semester: int, label: string}  $period
     * @return array<string, mixed>
     */
    private function finance(array $period): array
    {
        $tuition = StudentTuition::query()
            ->whereIn('school_year', [$period['school_year'], str_replace(' ', '', $period['school_year'])])
            ->where('semester', $period['semester']);

        $assessed = (float) (clone $tuition)->sum('overall_tuition');
        $paid = (float) (clone $tuition)->sum('paid');
        $adjustments = (float) (clone $tuition)->sum('assessment_adjustment');

        return [
            'academic_period' => $period,
            'currency' => $this->currency(),
            'records_in_scope' => (clone $tuition)->count(),
            'assessed_tuition' => round($assessed, 2),
            'tuition_adjustments' => round($adjustments, 2),
            'collected_payments' => round($paid, 2),
            'outstanding_balance' => round(max(0.0, $assessed - $paid), 2),
            'collection_efficiency_percent' => $assessed > 0 ? round(($paid / $assessed) * 100, 1) : null,
            'unavailable' => $this->financeGaps($tuition->clone()->count(), $assessed),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function financeGaps(int $recordsInScope, float $assessed): array
    {
        if ($recordsInScope === 0 || $assessed <= 0.0) {
            return [
                'assessed_tuition' => 'No tuition records exist for this term, so assessed, collected, and outstanding totals are all zero rather than missing.',
            ];
        }

        return [];
    }

    /**
     * @param  array{school_year: string, semester: int, label: string}  $period
     * @return array<string, mixed>
     */
    private function faculty(array $period): array
    {
        $classes = Classes::query()
            ->whereIn('school_year', [$period['school_year'], str_replace(' ', '', $period['school_year'])])
            ->where('semester', $period['semester']);

        $totalFaculty = Faculty::query()->count();
        $totalClasses = (clone $classes)->count();
        $assignedFaculty = (clone $classes)->whereNotNull('faculty_id')->distinct()->count('faculty_id');

        return [
            'academic_period' => $period,
            'faculty_count' => $totalFaculty,
            'classes_this_term' => $totalClasses,
            'classes_with_assigned_instructor' => (clone $classes)->whereNotNull('faculty_id')->count(),
            'unassigned_classes' => (clone $classes)->whereNull('faculty_id')->count(),
            'distinct_instructors_teaching' => $assignedFaculty,
            'instructors_without_classes' => max(0, $totalFaculty - $assignedFaculty),
            'average_classes_per_instructor' => $assignedFaculty > 0
                ? round($totalClasses / $assignedFaculty, 1)
                : null,
            'unavailable' => $totalFaculty === 0
                ? ['faculty_count' => 'No faculty records exist, so teaching load cannot be averaged.']
                : [],
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Student>  $query
     * @param  array<int, string>  $normaliseBlankTo
     * @return list<array{label: string, value: int}>
     */
    private function enrolledDistribution(
        \Illuminate\Database\Eloquent\Builder $query,
        string $column,
        array $normaliseBlankTo = [],
        int $limit = 50,
    ): array {
        $rows = $query
            ->toBase()
            ->select($column)
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy($column)
            ->orderByDesc('aggregate')
            ->limit($limit)
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $value = $row->{$column};
            $value = is_string($value) ? mb_trim($value) : $value;

            $label = in_array($value, ['', null], true)
                ? ($normaliseBlankTo[0] ?? 'Unspecified')
                : (string) $value;

            $out[] = [
                'label' => ucwords(str_replace('_', ' ', $label)),
                'value' => (int) $row->aggregate,
            ];
        }

        usort($out, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return $out;
    }

    /**
     * Normalize an aggregate result set from RegistrarAnalyticsService into
     * label/value pairs.
     *
     * @param  iterable<mixed>  $rows
     * @return list<array{label: string, title: string|null, value: int}>
     */
    private function labelCounts(
        iterable $rows,
        string $labelKey,
        ?string $titleKey = null,
        string $prefix = '',
        bool $humanise = false,
    ): array {
        $out = [];

        foreach ($rows as $row) {
            $data = is_array($row) ? $row : (array) $row;
            $label = (string) ($data[$labelKey] ?? 'Unassigned');
            $label = $humanise
                ? ucwords(str_replace('_', ' ', $label))
                : $prefix.$label;

            $out[] = [
                'label' => $label,
                'title' => $titleKey === null ? null : (string) ($data[$titleKey] ?? ''),
                'value' => (int) ($data['count'] ?? 0),
            ];
        }

        return $out;
    }

    private function currency(): string
    {
        $currency = GeneralSetting::query()->first()?->currency;

        return filled($currency) ? (string) $currency : 'PHP';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
