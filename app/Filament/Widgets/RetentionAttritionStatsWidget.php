<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Enums\AttritionCategory;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\DashboardStatisticsService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Override;

final class RetentionAttritionStatsWidget extends BaseWidget
{
    #[Override]
    protected static ?int $sort = 22;

    #[Override]
    protected ?string $heading = 'Retention & Attrition Analysis';

    #[Override]
    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $statistics = app(DashboardStatisticsService::class);

        // One statement resolves every attrition counter.
        $counts = $statistics->countsByConditions(
            model: Student::class,
            conditions: [
                'active' => ['status', 'in', [
                    StudentStatus::Enrolled->value,
                    StudentStatus::OnLeave->value,
                ]],
                'withdrawn' => ['status', '=', StudentStatus::Withdrawn->value],
                'dropped' => ['status', '=', StudentStatus::Dropped->value],
            ],
        );

        // Total active students (enrolled + on leave)
        $activeStudents = $counts['active'];

        // Attrition metrics
        $totalWithdrawn = $counts['withdrawn'];
        $totalDropped = $counts['dropped'];
        $totalAttrition = $totalWithdrawn + $totalDropped;

        // Calculate retention rate
        $totalEverEnrolled = $activeStudents + $totalAttrition;
        $retentionRate = $totalEverEnrolled > 0
            ? round(($activeStudents / $totalEverEnrolled) * 100, 1)
            : 0;

        // Top attrition reason
        $topAttritionReason = Student::whereIn('status', [
            StudentStatus::Withdrawn->value,
            StudentStatus::Dropped->value,
        ])
            ->whereNotNull('attrition_category')
            ->selectRaw('attrition_category, COUNT(*) as count')
            ->groupBy('attrition_category')
            ->orderByDesc('count')
            ->first();

        $topReasonLabel = $topAttritionReason
            ? AttritionCategory::from($topAttritionReason->attrition_category)->getLabel()
            : 'N/A';

        // Recent attrition (last 30 days)
        $recentAttrition = Student::whereIn('status', [
            StudentStatus::Withdrawn->value,
            StudentStatus::Dropped->value,
        ])
            ->where('updated_at', '>=', now()->subDays(30))
            ->count();

        return [
            Stat::make('Retention Rate', "{$retentionRate}%")
                ->description("{$activeStudents} of {$totalEverEnrolled} students retained")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color($retentionRate >= 80 ? 'success' : ($retentionRate >= 60 ? 'warning' : 'danger')),

            Stat::make('Total Attrition', number_format($totalAttrition))
                ->description("{$recentAttrition} in last 30 days")
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart($this->getAttritionTrend()),

            Stat::make('Top Attrition Reason', $topReasonLabel)
                ->description("Withdrawn: {$totalWithdrawn}, Dropped: {$totalDropped}")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('warning'),
        ];
    }

    private function getAttritionTrend(): array
    {
        $periods = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subMonths($i);

            // The stored timestamps are UTC, so the month is bounded in UTC
            // just like the `whereYear()`/`whereMonth()` pair this replaces.
            $start = Carbon::create((int) $date->year, (int) $date->month, 1, 0, 0, 0, 'UTC');

            if (! $start instanceof Carbon) {
                continue;
            }

            $periods[] = [
                'start' => $start,
                'end' => $start->copy()->endOfMonth(),
            ];
        }

        // One grouped statement resolves every month of the trend.
        $counts = app(DashboardStatisticsService::class)->countsByPeriods(
            model: Student::class,
            periods: $periods,
            table: 'students',
            timestampColumn: 'updated_at',
            filters: [
                ['status', 'in', [
                    StudentStatus::Withdrawn->value,
                    StudentStatus::Dropped->value,
                ]],
            ],
        );

        $data = [];

        foreach (array_keys($periods) as $index) {
            $data[] = $counts[$index]['total'] ?? 0;
        }

        return $data;
    }
}
