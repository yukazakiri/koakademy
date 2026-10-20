<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Override;

final class ApplicationEnrollmentStatsWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 20;

    #[Override]
    protected ?string $heading = 'Application vs Enrollment Tracking';

    #[Override]
    protected ?string $pollingInterval = '60s';

    /**
     * Memoised per-request daily counts, so the trend charts below share a
     * single grouped query instead of running one per day and per status.
     *
     * @var array<string, array<string, int>>|null
     */
    private ?array $dailyCounts = null;

    protected function getStats(): array
    {
        $statistics = app(DashboardStatisticsService::class);

        // One statement resolves every counter.
        $counts = $statistics->countsByConditions(
            model: Student::class,
            conditions: [
                'applicants' => ['status', '=', StudentStatus::Applicant->value],
                'enrolled' => ['status', '=', StudentStatus::Enrolled->value],
                'on_leave' => ['status', '=', StudentStatus::OnLeave->value],
            ],
        );

        $totalApplicants = $counts['applicants'];
        $totalEnrolled = $counts['enrolled'];
        $totalOnLeave = $counts['on_leave'];

        // Calculate conversion rate
        $totalProcessed = $totalApplicants + $totalEnrolled;
        $conversionRate = $totalProcessed > 0
            ? round(($totalEnrolled / $totalProcessed) * 100, 1)
            : 0;

        // Recent applicants (last 30 days)
        $recentApplicants = Student::where('status', StudentStatus::Applicant->value)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('Total Applicants', number_format($totalApplicants))
                ->description("{$recentApplicants} new in last 30 days")
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('warning')
                ->chart($this->getApplicantTrend()),

            Stat::make('Currently Enrolled', number_format($totalEnrolled))
                ->description("{$conversionRate}% conversion rate")
                ->descriptionIcon('heroicon-m-academic-cap')
                ->color('success')
                ->chart($this->getEnrollmentTrend()),

            Stat::make('On Leave', number_format($totalOnLeave))
                ->description('Students temporarily away')
                ->descriptionIcon('heroicon-m-pause-circle')
                ->color('info'),
        ];
    }

    /**
     * The daily counts of the last seven days, grouped by status.
     *
     * @return array<string, array<string, int>>
     */
    private function dailyCounts(): array
    {
        return $this->dailyCounts ??= app(DashboardStatisticsService::class)->dailyCounts(
            model: Student::class,
            labels: $this->trendDayLabels(),
            column: 'status',
            filters: [
                ['status', 'in', [
                    StudentStatus::Applicant->value,
                    StudentStatus::Enrolled->value,
                ]],
            ],
        );
    }

    /**
     * The `Y-m-d` labels of the last seven days, oldest first.
     *
     * @return array<int, string>
     */
    private function trendDayLabels(): array
    {
        $labels = [];

        for ($i = 6; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('Y-m-d');
        }

        return $labels;
    }

    private function getApplicantTrend(): array
    {
        $counts = $this->dailyCounts();

        $data = [];

        foreach ($this->trendDayLabels() as $label) {
            $data[] = $counts[$label][StudentStatus::Applicant->value] ?? 0;
        }

        return $data;
    }

    private function getEnrollmentTrend(): array
    {
        $counts = $this->dailyCounts();

        $data = [];

        foreach ($this->trendDayLabels() as $label) {
            $data[] = $counts[$label][StudentStatus::Enrolled->value] ?? 0;
        }

        return $data;
    }
}
