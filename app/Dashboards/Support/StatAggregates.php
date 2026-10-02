<?php

declare(strict_types=1);

namespace App\Dashboards\Support;

use App\Enums\StudentStatus;
use App\Enums\StudentType;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Transaction;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Illuminate\Support\Carbon;

/**
 * Institution-wide aggregates shared by the dashboard desks and the original portal payload.
 *
 * AdministratorPortalData::build() held these as private static methods, which made them
 * unreachable from a desk without duplicating the query. They live here so a desk and the
 * original dashboard compute the same figures from the same SQL.
 *
 * Every method is a pure reader. The aggregate queries deliberately collapse many counts into
 * a single grouped pass: a dashboard that issues twenty COUNT() queries is far slower than one
 * that issues two, and the panel renders on every navigation.
 */
final class StatAggregates
{
    /**
     * All student statistics in one aggregated query, replacing 16+ individual COUNT queries.
     *
     * @return object{ total: int, type_college: int, type_shs: int, type_tesda: int, type_dhrt: int, gender_male: int, gender_female: int, gender_other: int, gender_prefer_not_to_say: int, gender_unspecified: int, year_1: int, year_2: int, year_3: int, year_4: int, year_5: int, status_applicant: int, status_enrolled: int, status_on_leave: int }
     */
    public function studentStats(): object
    {
        return Student::query()
            ->selectRaw("
                count(*) as total,
                count(case when student_type = 'college' then 1 end) as type_college,
                count(case when student_type = 'shs' then 1 end) as type_shs,
                count(case when student_type = 'tesda' then 1 end) as type_tesda,
                count(case when student_type = 'dhrt' then 1 end) as type_dhrt,
                count(case when gender = 'male' then 1 end) as gender_male,
                count(case when gender = 'female' then 1 end) as gender_female,
                count(case when gender = 'other' then 1 end) as gender_other,
                count(case when gender = 'prefer_not_to_say' then 1 end) as gender_prefer_not_to_say,
                count(case when gender not in ('male', 'female', 'other', 'prefer_not_to_say') or gender is null then 1 end) as gender_unspecified,
                count(case when academic_year = 1 then 1 end) as year_1,
                count(case when academic_year = 2 then 1 end) as year_2,
                count(case when academic_year = 3 then 1 end) as year_3,
                count(case when academic_year = 4 then 1 end) as year_4,
                count(case when academic_year = 5 then 1 end) as year_5,
                count(case when status = 'applicant' then 1 end) as status_applicant,
                count(case when status = 'enrolled' then 1 end) as status_enrolled,
                count(case when status = 'on_leave' then 1 end) as status_on_leave
            ")
            ->first();
    }

    /**
     * @return array{applicants: int, enrolled: int, on_leave: int, conversion_rate: float}
     */
    public function applicationStats(object $stats): array
    {
        $applicants = (int) $stats->status_applicant;
        $enrolled = (int) $stats->status_enrolled;
        $onLeave = (int) $stats->status_on_leave;

        $processed = $applicants + $enrolled;

        return [
            'applicants' => $applicants,
            'enrolled' => $enrolled,
            'on_leave' => $onLeave,
            'conversion_rate' => $processed > 0 ? round(($enrolled / $processed) * 100, 1) : 0.0,
        ];
    }

    /**
     * @return array<int, array{type: string, label: string, count: int, percentage: float}>
     */
    public function studentTypeDistribution(object $stats, int $totalStudents): array
    {
        $columns = [
            StudentType::College->value => 'type_college',
            StudentType::SeniorHighSchool->value => 'type_shs',
            StudentType::TESDA->value => 'type_tesda',
            StudentType::DHRT->value => 'type_dhrt',
        ];

        return collect(StudentType::cases())
            ->map(function (StudentType $type) use ($stats, $totalStudents, $columns): array {
                $count = (int) ($stats->{$columns[$type->value] ?? ''} ?? 0);

                return [
                    'type' => $type->value,
                    'label' => $type->getLabel() ?? $type->value,
                    'count' => $count,
                    'percentage' => $totalStudents > 0 ? round(($count / $totalStudents) * 100, 1) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{gender: string, count: int}>
     */
    public function genderDistribution(object $stats): array
    {
        return [
            ['gender' => 'Male', 'count' => (int) $stats->gender_male],
            ['gender' => 'Female', 'count' => (int) $stats->gender_female],
            ['gender' => 'Other', 'count' => (int) $stats->gender_other],
            ['gender' => 'Prefer not to say', 'count' => (int) $stats->gender_prefer_not_to_say],
            ['gender' => 'Unspecified', 'count' => (int) $stats->gender_unspecified],
        ];
    }

    /**
     * @return array<int, array{year_level: string, count: int}>
     */
    public function yearLevelDistribution(object $stats): array
    {
        return [
            ['year_level' => '1st Year', 'count' => (int) $stats->year_1],
            ['year_level' => '2nd Year', 'count' => (int) $stats->year_2],
            ['year_level' => '3rd Year', 'count' => (int) $stats->year_3],
            ['year_level' => '4th Year', 'count' => (int) $stats->year_4],
            ['year_level' => 'Graduates', 'count' => (int) $stats->year_5],
        ];
    }

    /**
     * Monthly series for any Trend-supported query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return array<int, array{date: string, value: int}>
     */
    public function monthlySeries($query, ?string $status = null): array
    {
        if ($status !== null) {
            $query->where('status', $status);
        }

        return Trend::query($query)
            ->between(start: now()->startOfYear(), end: now()->endOfYear())
            ->perMonth()
            ->count()
            ->map(fn (TrendValue $value): array => [
                'date' => Carbon::parse($value->date)->startOfMonth()->toDateString(),
                'value' => (int) $value->aggregate,
            ])
            ->values()
            ->all();
    }

    /**
     * Percentage change between the last two non-zero points of a series.
     *
     * @param  array<int, array{date: string, value: float|int}>  $series
     */
    public function seriesTrend(array $series): float
    {
        $values = collect($series)
            ->pluck('value')
            ->filter(fn (float|int $value): bool => $value > 0)
            ->values();

        if ($values->count() < 2) {
            return 0.0;
        }

        $current = (float) $values->last();
        $previous = (float) $values->slice(-2, 1)->first();

        return $previous > 0 ? round((($current - $previous) / $previous) * 100, 1) : 0.0;
    }

    /**
     * Re-key a chart series as a {date, value} sparkline series.
     *
     * @param  array<int, array<string, mixed>>  $trend
     * @return array<int, array{date: string, value: int}>
     */
    public function toStatSeries(array $trend, string $valueKey): array
    {
        return collect($trend)
            ->map(fn (array $point): array => [
                'date' => (string) $point['date'],
                'value' => (int) ($point[$valueKey] ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Student status series, used to derive the conversion-rate sparkline.
     *
     * @return array<int, array{date: string, value: int}>
     */
    public function studentStatusSeries(StudentStatus $status): array
    {
        return $this->monthlySeries(Student::query(), $status->value);
    }

    /**
     * Collections figures for an academic period, limited to one school.
     *
     * Period totals are filtered through whereHas() on the related enrollment, and the
     * outstanding/fully-paid split reuses the same base query. raw_total_amount is an accessor
     * on Transaction, so today's collection is summed in PHP.
     *
     * None of student_transactions, transactions or student_tuition carries a school_id and
     * none of those models has the BelongsToSchool scope, so a school filter has to travel
     * through the enrollment relation. Without it a multi-school installation would aggregate
     * every school's payments into one cashier's totals. Pass null only where a caller has
     * already established there is a single school.
     *
     * @return array{
     *     total_revenue: float,
     *     total_collectibles: float,
     *     total_assessed: float,
     *     collection_rate: float,
     *     fully_paid_count: int,
     *     outstanding_count: int,
     *     today_collection: float,
     *     today_transactions: int
     * }
     */
    public function financeSnapshot(string $schoolYear, int $semester, ?School $school = null): array
    {
        $schoolId = $school?->id;

        $periodTuition = StudentTuition::query()
            ->whereHas('enrollment', function ($query) use ($schoolYear, $semester, $schoolId): void {
                $query->forAcademicPeriod($schoolYear, $semester);

                if ($schoolId !== null) {
                    $query->where('school_id', $schoolId);
                }
            });

        $totalRevenue = (float) StudentTransaction::query()
            ->whereHas('transaction', function ($query) use ($schoolYear, $semester): void {
                $query->forAcademicPeriod($schoolYear, $semester);
            })
            // StudentTransaction has no school scope, so constrain via the enrollment it belongs to.
            ->when($schoolId !== null, fn ($query) => $query->whereHas(
                'enrollment',
                fn ($enrollment) => $enrollment->where('school_id', $schoolId),
            ))
            ->sum('amount');

        $totalAssessed = (float) (clone $periodTuition)->sum('overall_tuition');

        $todayTransactions = $this->todayTransactions($schoolId);

        return [
            'total_revenue' => $totalRevenue,
            'total_collectibles' => (float) (clone $periodTuition)->sum('total_balance'),
            'total_assessed' => $totalAssessed,
            'collection_rate' => $totalAssessed > 0 ? round(($totalRevenue / $totalAssessed) * 100, 1) : 0.0,
            'fully_paid_count' => (clone $periodTuition)->where('total_balance', '<=', 0)->count(),
            'outstanding_count' => (clone $periodTuition)->where('total_balance', '>', 0)->count(),
            'today_collection' => (float) $todayTransactions->sum(fn (Transaction $transaction): float => $transaction->raw_total_amount),
            'today_transactions' => $todayTransactions->count(),
        ];
    }

    /**
     * Today's transactions, optionally limited to one school.
     *
     * `settlements` must be selected for raw_total_amount to be readable, because the accessor
     * decodes that attribute; a grouped or narrowed select would silently total to zero.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Transaction>
     */
    private function todayTransactions(?int $schoolId)
    {
        return Transaction::query()
            ->whereBetween('transaction_date', [now()->startOfDay(), now()->endOfDay()])
            ->when($schoolId !== null, fn ($query) => $query
                // Transaction has no school scope either; reach it through the student
                // transactions that point at this transaction.
                ->whereHas('studentTransactions.enrollment', fn ($enrollment) => $enrollment->where('school_id', $schoolId)))
            ->get();
    }
}
